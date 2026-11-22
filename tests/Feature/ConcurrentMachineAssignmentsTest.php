<?php

namespace Tests\Feature;

use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\WorkerPushNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentMachineAssignmentsTest extends TestCase
{
    public static function cases(): array
    {
        $cases = [];
        foreach (['excavator', 'truck'] as $type) {
            $cases["$type same machine"] = [$type, 'machine', 1, 1];
            $cases["$type same worker"] = [$type, 'worker', 1, 1];
            $cases["$type self same machine"] = [$type, 'self-machine', 1, 0];
            $cases["$type self same worker"] = [$type, 'self-worker', 1, 0];
            $cases["$type same takeover"] = [$type, 'takeover', 1, 1];
            $cases["$type conflicting updates"] = [$type, 'update', 2, 2];
        }

        return $cases;
    }

    #[DataProvider('cases')]
    public function test_parallel_machine_writes(string $type, string $case, int $expectedRows, int $expectedAttendances): void
    {
        $port = getenv('BUILDERLOG_M3_MYSQL_PORT');
        if ($port === false) {
            $this->markTestSkipped('Requires the dedicated MySQL 8.4 M3 test database.');
        }

        $directory = sys_get_temp_dir().'/builderlog-m3-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $processes = [];
        $connection = [
            'driver' => 'mysql',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => $port,
            'database' => 'builderlog_m3_test_core',
            'username' => 'root',
            'password' => '',
            'unix_socket' => '',
        ];

        try {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql' => $connection + config('database.connections.mysql'),
            ]);
            DB::purge('mysql');
            $this->assertStringStartsWith('8.4.', DB::selectOne('select version() as version')->version);
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

            $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
            $company = Company::factory()->create();
            $daily = DailyLog::factory()->for($company)->create(['date' => today()]);
            $manager = Worker::factory()->for($company)->siteManager()->create();
            $daily->siteManagers()->attach($manager);
            $secondDaily = $case === 'takeover'
                ? $daily
                : DailyLog::factory()->for($company)->withManager($manager)->create(['date' => today()]);
            $role = $type === 'truck' ? WorkerRole::DRIVER : WorkerRole::OPERATOR;
            [$first, $second] = Worker::factory()->for($company)->count(2)->create(['role' => $role])->all();
            [$machine, $other] = Machine::factory()->for($company)->count(2)->create([
                'type' => $type,
                'owner_type' => 'company',
                'owner_id' => $company->id,
            ])->all();

            $prefix = "/api/v1/{$type}-logs";
            $date = today()->toDateString();
            $firstPayload = [
                'machine_id' => $machine->id,
                'worker_id' => $first->id,
                'site_manager_started_at' => "$date 08:00:00",
                'site_manager_finished_at' => "$date 10:00:00",
            ];
            $secondPayload = $firstPayload;
            $firstUrl = "$prefix/daily-logs/{$daily->id}";
            $secondUrl = "$prefix/daily-logs/{$secondDaily->id}";
            $method = 'POST';
            $firstToken = $daily->siteManagers()->firstOrFail()->createToken('race first')->plainTextToken;
            $secondToken = $manager->createToken('race second')->plainTextToken;

            if ($case === 'machine') {
                $secondPayload['worker_id'] = $second->id;
            }
            if ($case === 'worker') {
                $secondPayload['machine_id'] = $other->id;
            }
            if (str_starts_with($case, 'self-')) {
                $firstPayload = [
                    'machine_id' => $machine->id,
                    'construction_site_id' => $daily->construction_site_id,
                    'operator_started_at' => "$date 08:00:00",
                ];
                $secondPayload = [
                    'machine_id' => $case === 'self-worker' ? $other->id : $machine->id,
                    'construction_site_id' => $secondDaily->construction_site_id,
                ] + $firstPayload;
                $firstUrl = $secondUrl = "$prefix/{$role->value}";
                $secondActor = $case === 'self-worker' ? $first : $second;
                $firstToken = $first->createToken('parallel self first')->plainTextToken;
                $secondToken = $secondActor->createToken('parallel self second')->plainTextToken;
            }
            if ($case === 'takeover') {
                $this->withToken($first->createToken('self assignment')->plainTextToken)
                    ->postJson("$prefix/{$role->value}", [
                        'machine_id' => $machine->id,
                        'construction_site_id' => $daily->construction_site_id,
                        'operator_started_at' => "$date 08:00:00",
                    ])->assertOk();
                $this->assertSame(0, WorkerAttendance::count());
            }
            if ($case === 'update') {
                $firstLogId = $this->withToken($firstToken)
                    ->postJson($firstUrl, $firstPayload)->assertOk()->json('data.id');
                $this->app['auth']->forgetGuards();
                $secondLogId = $this->withToken($secondToken)->postJson($secondUrl, [
                    'worker_id' => $second->id,
                    'site_manager_started_at' => "$date 12:00:00",
                    'site_manager_finished_at' => "$date 14:00:00",
                ] + $firstPayload)->assertOk()->json('data.id');
                $firstUrl = "$prefix/$firstLogId";
                $secondUrl = "$prefix/$secondLogId";
                $method = 'PATCH';
                $firstPayload = [
                    'site_manager_started_at' => "$date 08:00:00",
                    'site_manager_finished_at' => "$date 11:00:00",
                ];
                $secondPayload = [
                    'site_manager_started_at' => "$date 10:30:00",
                    'site_manager_finished_at' => "$date 14:00:00",
                ];
            }

            foreach ([[$firstUrl, $firstToken, $firstPayload], [$secondUrl, $secondToken, $secondPayload]] as [$url, $token, $payload]) {
                $process = $this->requestProcess($directory, $port, $url, $method, $token, $payload);
                $process->start();
                $processes[] = $process;
            }

            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn (string $type, string $output) => str_contains($output, 'READY')));
            }
            touch($directory.'/start');

            $statuses = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                [, $json] = explode("READY\n", $process->getOutput(), 2);
                $response = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                $statuses[] = $response['status'];
                if ($response['status'] !== 200) {
                    $this->assertSame('App\\Exceptions\\BusinessException', $response['exception'], $json);
                    $this->assertStringContainsString('during the selected period.', $response['message']);
                }
            }
            sort($statuses);
            $this->assertSame([200, 500], $statuses);
            $this->assertSame($expectedRows, MachineAssignment::count());
            $this->assertSame($expectedAttendances, WorkerAttendance::count());
            if ($case === 'takeover') {
                $takeover = AuditLog::where('event', LogEvent::MACHINE_ASSIGNMENT_TAKEN_OVER)->sole();
                $this->assertSame($takeover->actor_id, MachineAssignment::sole()->site_manager_id);
                $this->assertSame($takeover->actor_id, WorkerAttendance::sole()->site_manager_id);
            }
            if ($case === 'update') {
                $model = $type === 'truck' ? TruckLog::class : ExcavatorLog::class;
                $periods = $model::orderBy('site_manager_started_at')->get();
                $this->assertLessThanOrEqual($periods[1]->site_manager_started_at, $periods[0]->site_manager_finished_at);
            }
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::disconnect();
            File::deleteDirectory($directory);
        }
    }

    private function requestProcess(string $directory, string $port, string $url, string $method, string $token, array $payload): Process
    {
        $script = <<<'PHP_SCRIPT'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$push = Mockery::mock(App\Services\WorkerPushNotificationService::class);
$push->shouldReceive('send')->zeroOrMoreTimes();
$app->instance(App\Services\WorkerPushNotificationService::class, $push);

Illuminate\Support\Facades\DB::listen(function ($query) {
    if ($query->connection->transactionLevel() > 0 && str_contains($query->sql, '`companies`') && str_contains($query->sql, 'for update')) {
        usleep(150_000);
    }
});

echo "READY\n";
$deadline = microtime(true) + 15;
while (! is_file($argv[1])) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(1_000);
}
$request = Illuminate\Http\Request::create(
    $argv[2],
    $argv[3],
    server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$argv[4], 'CONTENT_TYPE' => 'application/json'],
    content: $argv[5],
);
$response = $kernel->handle($request);
$result = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['status' => $response->getStatusCode(), 'exception' => $result['exception'] ?? null, 'message' => $result['message'] ?? null], JSON_THROW_ON_ERROR);
PHP_SCRIPT;

        return new Process([PHP_BINARY, '-r', $script, $directory.'/start', $url, $method, $token, json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), [
            'APP_CONFIG_CACHE' => $directory.'/no-config',
            'APP_ROUTES_CACHE' => $directory.'/no-routes',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'true',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'builderlog_m3_test_core',
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => $port,
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            'DB_URL' => '',
            'DB_SOCKET' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ], timeout: 20);
    }
}
