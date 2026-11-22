<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentWorkerAttendanceTest extends TestCase
{
    public function test_same_worker_cannot_be_added_concurrently_on_two_sites(): void
    {
        $directory = sys_get_temp_dir().'/builderlog-m2-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $processes = [];

        try {
            $connection = $this->useIsolatedDatabase($directory);
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();

            $company = Company::factory()->create();
            $worker = Worker::factory()->for($company)->create();
            $date = today()->toDateString();
            $logs = DailyLog::factory()->for($company)->count(2)->create(['date' => $date]);
            self::assertNotSame($logs[0]->construction_site_id, $logs[1]->construction_site_id);

            foreach ($logs as $log) {
                $process = $this->attendanceProcess(
                    $connection,
                    $directory,
                    $log->id,
                    $log->siteManagers()->firstOrFail()->createToken('parallel attendance')->plainTextToken,
                    $worker->id,
                    $date,
                );
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                self::assertTrue($process->waitUntil(fn (string $type, string $output) => str_contains($output, 'READY')), $process->getErrorOutput());
            }
            touch($directory.'/start');

            $responses = [];
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput().$process->getOutput());
                [, $json] = explode("READY\n", $process->getOutput(), 2);
                $responses[] = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
            }

            $statuses = array_column($responses, 'status');
            sort($statuses);

            self::assertSame([200, 409], $statuses);
            $attendance = WorkerAttendance::query()->where('worker_id', $worker->id)->sole();
            self::assertSame($date, $attendance->ordinary_worker_day);
            self::assertFalse($worker->fresh()->is_available);
            self::assertSame(1, DB::table('activity_logs')
                ->where('subject_type', 'worker_attendance')
                ->where('subject_id', $attendance->id)->count());

            // Verify the database constraint independently of the endpoint's worker lock and precheck.
            $attributes = collect($attendance->getAttributes())->except('id')->all();
            $attributes['daily_log_id'] = $logs->firstWhere('id', '!=', $attendance->daily_log_id)->id;
            $this->expectException(UniqueConstraintViolationException::class);
            DB::table('worker_attendances')->insert($attributes);
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::disconnect();
            File::deleteDirectory($directory);
        }
    }

    private function useIsolatedDatabase(string $directory): array
    {
        $port = getenv('BUILDERLOG_M2_MYSQL_PORT');
        if ($port !== false) {
            $connection = [
                'driver' => 'mysql',
                'url' => null,
                'host' => '127.0.0.1',
                'port' => $port,
                'database' => 'builderlog_m2_test_core',
                'username' => 'root',
                'password' => '',
                'unix_socket' => '',
            ];
        } else {
            $connection = [
                'driver' => 'sqlite',
                'url' => null,
                'database' => $directory.'/db.sqlite',
                'foreign_key_constraints' => true,
                'transaction_mode' => 'DEFERRED',
            ];
            touch($connection['database']);
        }

        config([
            'database.default' => $connection['driver'],
            'database.connections.'.$connection['driver'] => $connection + config('database.connections.'.$connection['driver']),
        ]);
        DB::purge($connection['driver']);

        return $connection;
    }

    private function attendanceProcess(array $connection, string $directory, int $logId, string $token, int $workerId, string $date): Process
    {
        $script = <<<'PHP_SCRIPT'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$delayed = false;
Illuminate\Support\Facades\DB::listen(function ($query) use (&$delayed) {
    if (! $delayed && $query->connection->transactionLevel() > 0 && str_contains($query->sql, 'workers')) {
        $delayed = true;
        usleep(100_000);
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
    '/api/v1/daily-logs/'.$argv[2].'/worker-attendances',
    'POST',
    ['worker_id' => $argv[4], 'started_at' => $argv[5].' 08:00:00'],
    server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$argv[3]],
);
$response = $kernel->handle($request);
$body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['status' => $response->getStatusCode(), 'id' => $body['data']['id'] ?? null], JSON_THROW_ON_ERROR);
PHP_SCRIPT;

        return new Process([PHP_BINARY, '-r', $script, $directory.'/start', (string) $logId, $token, (string) $workerId, $date], base_path(), [
            'APP_CONFIG_CACHE' => $directory.'/no-config',
            'APP_ROUTES_CACHE' => $directory.'/no-routes',
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => $connection['driver'],
            'DB_DATABASE' => $connection['database'],
            'DB_HOST' => $connection['host'] ?? '127.0.0.1',
            'DB_PORT' => $connection['port'] ?? '3306',
            'DB_USERNAME' => $connection['username'] ?? '',
            'DB_PASSWORD' => '',
            'DB_URL' => '',
            'DB_SOCKET' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
        ], timeout: 20);
    }
}
