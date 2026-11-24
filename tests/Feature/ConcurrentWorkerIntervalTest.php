<?php

namespace Tests\Feature;

use App\Enums\WorkerRole;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentWorkerIntervalTest extends TestCase
{
    public static function races(): array
    {
        return [
            'overlapping manual creates' => ['overlap', [200, 409], 1],
            'adjacent manual creates' => ['adjacent', [200, 200], 2],
            'manual and machine overlap' => ['mixed', [], 1],
            'concurrent edits create an overlap' => ['update', [200, 409], 2],
        ];
    }

    #[DataProvider('races')]
    public function test_worker_interval_writes_serialize(string $scenario, array $expected, int $count): void
    {
        $port = getenv('BUILDERLOG_INTERVAL_MYSQL_PORT');
        if ($port === false) {
            $this->markTestSkipped('Requires the dedicated MySQL 8.4 interval test database.');
        }

        $directory = sys_get_temp_dir().'/builderlog-interval-'.bin2hex(random_bytes(8));
        mkdir($directory);
        config([
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'driver' => 'mysql', 'url' => null, 'host' => '127.0.0.1', 'port' => $port,
                'database' => 'builderlog_interval_race', 'username' => 'root', 'password' => '', 'unix_socket' => '',
            ] + config('database.connections.mysql'),
        ]);
        DB::purge('mysql');
        $processes = [];

        try {
            $this->assertStringStartsWith('8.4.', DB::selectOne('select version() as version')->version);
            $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();

            $date = today()->toDateString();
            $a = DailyLog::factory()->create(['date' => $date]);
            $b = DailyLog::factory()->for($a->company)->create(['date' => $date]);
            $worker = Worker::factory()->for($a->company)->create([
                'role' => $scenario === 'mixed' ? WorkerRole::OPERATOR : WorkerRole::WORKER,
            ]);
            $payload = fn (string $start, string $end) => [
                'worker_id' => $worker->id, 'started_at' => "$date $start", 'finished_at' => "$date $end",
            ];
            $first = $payload('08:00:00', '12:00:00');
            $second = $scenario === 'adjacent' ? $payload('12:00:00', '16:00:00') : $payload('09:00:00', '13:00:00');
            $prefix = '/api/v1/daily-logs';
            $firstUrl = "$prefix/{$a->id}/worker-attendances";
            $secondUrl = "$prefix/{$b->id}/worker-attendances";
            $method = 'POST';
            if ($scenario === 'mixed') {
                $machine = Machine::factory()->for($a->company)->create(['owner_type' => 'company', 'owner_id' => $a->company_id]);
                $secondUrl = "/api/v1/excavator-logs/daily-logs/{$b->id}";
                $second = ['machine_id' => $machine->id, 'worker_id' => $worker->id,
                    'site_manager_started_at' => $second['started_at'], 'site_manager_finished_at' => $second['finished_at']];
            }
            if ($scenario === 'update') {
                $firstAttendance = $this->record($a, $worker, $first);
                $secondAttendance = $this->record($b, $worker, $payload('14:00:00', '18:00:00'));
                $firstUrl .= '/'.$firstAttendance->id;
                $secondUrl .= '/'.$secondAttendance->id;
                $method = 'PATCH';
                $first = ['finished_at' => "$date 13:15:00"];
                $second = ['started_at' => "$date 12:45:00"];
            }

            foreach ([[$a, $firstUrl, $first], [$b, $secondUrl, $second]] as [$log, $url, $body]) {
                $token = $log->siteManagers()->sole()->createToken('interval race')->plainTextToken;
                $process = $this->makeRequest($directory, $port, $url, $method, $token, $body);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn (string $kind, string $output) => str_contains($output, 'READY')));
            }
            touch($directory.'/start');
            $statuses = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput().$process->getOutput());
                [, $json] = explode("READY\n", $process->getOutput(), 2);
                $response = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
                $statuses[] = $response['status'];
                if ($response['status'] === 409) {
                    $this->assertStringContainsString('overlapping period', $response['message']);
                }
                if ($response['status'] === 500) {
                    $this->assertStringContainsString('selected period', $response['message']);
                }
            }
            sort($statuses);
            if ($scenario === 'mixed') {
                $this->assertContains($statuses, [[200, 409], [200, 500]]);
            } else {
                $this->assertSame($expected, $statuses);
            }
            $this->assertSame($count, WorkerAttendance::count());
            $ordered = WorkerAttendance::orderBy('started_at')->get();
            if ($ordered->count() === 2) {
                $this->assertTrue($ordered[0]->finished_at->lessThanOrEqualTo($ordered[1]->started_at));
            }
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::purge('mysql');
            File::deleteDirectory($directory);
        }
    }

    private function record(DailyLog $log, Worker $worker, array $period): WorkerAttendance
    {
        $managerId = $log->siteManagers()->sole()->id;

        return WorkerAttendance::create($period + [
            'company_id' => $worker->company_id, 'daily_log_id' => $log->id, 'construction_site_id' => $log->construction_site_id,
            'date' => $log->date, 'site_manager_id' => $managerId, 'created_by' => $managerId,
        ]);
    }

    private function makeRequest(string $directory, string $port, string $url, string $method, string $token, array $body): Process
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
    if (str_contains($query->sql, '`companies`') && str_contains($query->sql, 'for update')) {
        usleep(150000);
    }
});
echo "READY\n";
$deadline = microtime(true) + 15;
while (! is_file($argv[1])) {
    if (microtime(true) > $deadline) {
        exit(2);
    }
    usleep(1000);
}
$request = Illuminate\Http\Request::create($argv[2], $argv[3], server: [
    'HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$argv[4], 'CONTENT_TYPE' => 'application/json',
], content: $argv[5]);
$response = $kernel->handle($request);
$body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['status' => $response->getStatusCode(), 'message' => $body['message'] ?? ''], JSON_THROW_ON_ERROR);
PHP_SCRIPT;

        return new Process([PHP_BINARY, '-r', $script, $directory.'/start', $url, $method, $token, json_encode($body, JSON_THROW_ON_ERROR)], base_path(), [
            'APP_CONFIG_CACHE' => $directory.'/no-config', 'APP_ROUTES_CACHE' => $directory.'/no-routes',
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'true', 'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => 'builderlog_interval_race', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => $port,
            'DB_USERNAME' => 'root', 'DB_PASSWORD' => '', 'DB_URL' => '', 'DB_SOCKET' => '',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array',
        ], timeout: 25);
    }
}
