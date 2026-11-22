<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrentDailyLogSelectionTest extends TestCase
{
    #[DataProvider('managerPairs')]
    public function test_concurrent_first_selection_creates_one_log_and_one_membership_per_manager(bool $sameManager): void
    {
        $directory = sys_get_temp_dir().'/builderlog-m1-'.bin2hex(random_bytes(8));
        mkdir($directory);
        $processes = [];

        try {
            $connection = $this->useIsolatedDatabase($directory);
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();

            $company = Company::factory()->create();
            $site = ConstructionSite::factory()->for($company)->create();
            $a = Worker::factory()->for($company)->siteManager()->create();
            $b = $sameManager ? $a : Worker::factory()->for($company)->siteManager()->create();
            $site->siteManagers()->attach(array_unique([$a->id, $b->id]));

            foreach ([$a, $b] as $worker) {
                $process = $this->selectionProcess(
                    $connection,
                    $directory,
                    $site->id,
                    $worker->createToken('parallel selection')->plainTextToken,
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

            self::assertSame([200, 201], $statuses);
            self::assertSame($responses[0]['id'], $responses[1]['id']);
            $dailyLog = $site->dailyLogs()->sole();
            self::assertSame($dailyLog->id, $responses[0]['id']);
            self::assertSame(array_values(array_unique([$a->id, $b->id])), $dailyLog->siteManagers()->reorder()->orderBy('workers.id')->pluck('workers.id')->all());
            self::assertSame(1, DB::table('activity_logs')->where('daily_log_id', $dailyLog->id)->count());
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            DB::disconnect();
            File::deleteDirectory($directory);
        }
    }

    public static function managerPairs(): array
    {
        return [
            'different managers' => [false],
            'same manager' => [true],
        ];
    }

    private function useIsolatedDatabase(string $directory): array
    {
        $port = getenv('BUILDERLOG_M1_MYSQL_PORT');
        if ($port !== false) {
            $connection = [
                'driver' => 'mysql',
                'url' => null,
                'host' => '127.0.0.1',
                'port' => $port,
                'database' => 'builderlog_m1_test_core',
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

    private function selectionProcess(array $connection, string $directory, int $siteId, string $token): Process
    {
        $script = <<<'PHP_SCRIPT'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$delayed = false;
Illuminate\Support\Facades\DB::listen(function ($query) use (&$delayed) {
    if (! $delayed && $query->connection->transactionLevel() > 0 && str_contains($query->sql, 'construction_sites')) {
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
    '/api/v1/construction-sites/'.$argv[2].'/select',
    'POST',
    server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$argv[3]],
);
$response = $kernel->handle($request);
$body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
echo json_encode(['status' => $response->getStatusCode(), 'id' => $body['data']['id'] ?? null], JSON_THROW_ON_ERROR);
PHP_SCRIPT;

        return new Process([PHP_BINARY, '-r', $script, $directory.'/start', (string) $siteId, $token], base_path(), [
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
