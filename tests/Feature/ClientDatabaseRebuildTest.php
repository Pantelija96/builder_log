<?php

namespace Tests\Feature;

use App\Services\Database\ClientDatabaseConnection;
use App\Services\Database\ClientDatabaseValidator;
use Generator;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ClientDatabaseRebuildTest extends TestCase
{
    private string $directory;

    private string $database;

    private PDO $origin;

    private array $options;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();

        $originPort = $this->loopbackPort('BUILDERLOG_REBUILD_TEST_ORIGIN_PORT');
        $verifyPort = $this->loopbackPort('BUILDERLOG_REBUILD_TEST_VERIFY_PORT');
        $client = getenv('BUILDERLOG_REBUILD_TEST_MYSQL');
        $dump = getenv('BUILDERLOG_REBUILD_TEST_DUMP');
        if ($originPort === $verifyPort || ! $client || ! $dump) {
            $this->markTestSkipped('Requires two disposable loopback MySQL servers and local MySQL client and dump executables.');
        }
        $admins = [];
        foreach ([$originPort => 'bl_rebuild_origin_guard', $verifyPort => 'bl_rebuild_verify_guard'] as $port => $guard) {
            $admins[$port] = new PDO("mysql:host=127.0.0.1;port=$port", 'root', '', [PDO::ATTR_TIMEOUT => 2]);
            self::assertSame($guard, $admins[$port]->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$guard'")->fetchColumn());
        }

        $suffix = bin2hex(random_bytes(4));
        $this->database = 'bl_rebuild_origin_'.$suffix;
        $rehearsal = 'bl_rebuild_rehearsal_'.$suffix;
        $verifier = 'bl_rebuild_verify_'.$suffix;
        $admins[$originPort]->exec('CREATE DATABASE '.$this->database);
        $admins[$verifyPort]->exec("CREATE DATABASE $rehearsal; CREATE DATABASE $verifier");
        $common = ['host' => '127.0.0.1', 'username' => 'root', 'password' => ''];
        $source = $common + ['name' => 'source', 'database' => $this->database, 'port' => $originPort];
        $target = $common + ['name' => 'rehearsal', 'database' => $rehearsal, 'port' => $verifyPort];
        $this->directory = sys_get_temp_dir().'/builderlog-rebuild-test-'.Str::uuid();
        config([
            'client_database.storage' => $this->directory,
            'client_database.clients' => ['test' => ['source' => $source, 'destination' => $target]],
            'client_database.rebuild_verifier' => $common + ['database' => $verifier, 'port' => $verifyPort, 'mysql' => $client],
            'database.connections.rebuild_fixture' => app(ClientDatabaseConnection::class)->config($source),
        ]);
        $this->origin = DB::connection('rebuild_fixture')->getPdo();
        self::assertSame(0, Artisan::call('migrate', ['--database' => 'rebuild_fixture', '--force' => true]));
        $this->origin->exec("INSERT INTO companies (id,name) VALUES (17,'Fixture');
            INSERT INTO workers (id,company_id,first_name,last_name,role,password) VALUES (41,17,'Test','Manager','site_manager','preserved-hash');
            INSERT INTO construction_sites (id,company_id,name) VALUES (29,17,'Site');
            INSERT INTO daily_logs (id,company_id,construction_site_id,date,created_at) VALUES (59,17,29,'2026-10-12','2026-10-12 05:03:07');
            INSERT INTO sessions (id,payload,last_activity) VALUES ('fixture','old-session',1);
            ALTER TABLE workers DROP COLUMN display_order;
            ALTER TABLE daily_logs ADD COLUMN site_manager_id BIGINT UNSIGNED NULL;
            UPDATE daily_logs SET site_manager_id=41;
            ALTER TABLE daily_logs MODIFY COLUMN site_manager_id BIGINT UNSIGNED NOT NULL,
                ADD CONSTRAINT daily_logs_site_manager_id_foreign FOREIGN KEY (site_manager_id)
                REFERENCES workers(id) ON UPDATE CASCADE ON DELETE RESTRICT;
            DROP TABLE daily_log_site_managers");

        $backup = (string) Str::uuid();
        $workspace = $this->directory.'/test/rebuild-'.$backup;
        mkdir($workspace, 0700, true);
        $process = new Process([
            $dump, '--no-defaults', '--protocol=TCP', '--host=127.0.0.1', '--port='.$originPort, '--user=root',
            '--single-transaction', '--no-tablespaces', '--skip-dump-date', '--set-gtid-purged=OFF', $this->database,
        ]);
        self::assertSame(0, $process->run());
        file_put_contents($workspace.'/legacy.sql', $process->getOutput());
        chmod($workspace.'/legacy.sql', 0600);

        self::assertSame(0, $this->command('client:export', ['--client' => 'test', '--source' => 'source']), $this->output);
        $package = dirname(glob($this->directory.'/test/*/manifest.json')[0]);
        $export = basename($package);
        self::assertSame(0, $this->command('client:restore', ['--client' => 'test', '--export' => $export, '--destination' => 'rehearsal']), $this->output);
        preg_match('/restore-(.+)\.json$/', glob($package.'/restore-*.json')[0], $matches);
        $this->options = [
            '--client' => 'test', '--source' => 'source', '--backup' => $backup, '--export' => $export, '--rehearsal' => $matches[1],
        ];
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    public function test_successful_rebuild_and_safety_checks_leave_source_unchanged_until_confirmation(): void
    {
        self::assertSame(1, $this->rebuild());
        self::assertStringContainsString('exact database name', $this->output);
        self::assertSame(1, $this->rebuild(['--confirm-database' => 'wrong', '--writes-stopped' => true]));
        self::assertSame(1, $this->rebuild(['--dry-run' => true, '--backup' => (string) Str::uuid()]));
        self::assertStringContainsString('rollback SQL backup is missing', $this->output);

        $package = $this->directory.'/test/'.$this->options['--export'];
        $file = $package.'/workers.jsonl';
        $original = file_get_contents($file);
        file_put_contents($file, "bad\n");
        self::assertSame(1, $this->rebuild(['--dry-run' => true]));
        self::assertStringContainsString('corrupt export file', $this->output);
        file_put_contents($file, $original);

        $manifest = $package.'/manifest.json';
        $original = file_get_contents($manifest);
        $modified = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
        $modified['source']['identity']['server_uuid'] = (string) Str::uuid();
        file_put_contents($manifest, json_encode($modified, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->rebuild(['--dry-run' => true]));
        self::assertStringContainsString('target identity differs', $this->output);
        file_put_contents($manifest, $original);

        self::assertSame(0, $this->rebuild(['--dry-run' => true]), $this->output);
        self::assertSame('41', $this->origin->query('SELECT site_manager_id FROM daily_logs WHERE id=59')->fetchColumn());
        self::assertSame('1', $this->origin->query('SELECT COUNT(*) FROM sessions')->fetchColumn());

        $this->origin->exec("UPDATE sessions SET payload='changed after checkpoint' WHERE id='fixture'");
        self::assertSame(1, $this->confirmedRebuild());
        self::assertStringContainsString('differs from the verified dry-run', $this->output);
        $this->origin->exec("UPDATE sessions SET payload='old-session' WHERE id='fixture'");

        $originalMapping = config('client_database.clients.test.destination.database');
        config(['client_database.clients.test.destination.database' => $this->database]);
        self::assertSame(1, $this->command('client:restore', ['--client' => 'test', '--destination' => 'rehearsal', '--export' => $this->options['--export']]));
        self::assertStringContainsString('different transfer role', $this->output);
        config(['client_database.clients.test.destination.database' => $originalMapping]);

        self::assertSame(0, $this->confirmedRebuild(), $this->output);
        self::assertSame($this->database, $this->origin->query('SELECT DATABASE()')->fetchColumn());
        self::assertSame('41', $this->origin->query('SELECT site_manager_id FROM daily_log_site_managers WHERE daily_log_id=59')->fetchColumn());
        self::assertSame('preserved-hash', $this->origin->query('SELECT password FROM workers WHERE id=41')->fetchColumn());
        self::assertSame('0', $this->origin->query('SELECT display_order FROM workers WHERE id=41')->fetchColumn());
        self::assertSame('0', $this->origin->query('SELECT COUNT(*) FROM sessions')->fetchColumn());
    }

    public function test_migration_failure_stops_without_retry(): void
    {
        self::assertSame(0, $this->rebuild(['--dry-run' => true]), $this->output);
        $attempts = 0;
        Event::listen(MigrationsStarted::class, function () use (&$attempts): void {
            $attempts++;
            throw new RuntimeException('Injected migration failure.');
        });
        self::assertSame(1, $this->confirmedRebuild());
        self::assertStringContainsString('destination migrations', $this->output);
        self::assertSame(1, $attempts);
        self::assertFalse($this->origin->query("SHOW TABLES LIKE 'companies'")->fetchColumn());
    }

    public function test_restore_failure_rolls_back_data_without_retry(): void
    {
        self::assertSame(0, $this->rebuild(['--dry-run' => true]), $this->output);
        $validator = app(ClientDatabaseValidator::class);
        $reads = 0;
        $this->partialMock(ClientDatabaseValidator::class)
            ->shouldReceive('rowsFromFile')
            ->andReturnUsing(function (string $path, string $table, array $columns) use ($validator, &$reads): Generator {
                if ($table === 'construction_sites' && ++$reads === 3) {
                    throw new RuntimeException('Injected restore failure.');
                }

                return $validator->rowsFromFile($path, $table, $columns);
            });
        self::assertSame(1, $this->confirmedRebuild());
        self::assertStringContainsString('import construction_sites', $this->output);
        self::assertSame('0', $this->origin->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame('50', $this->origin->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        self::assertSame(3, $reads);
    }

    private function loopbackPort(string $key): int
    {
        $port = filter_var(getenv($key), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
        if ($port === false) {
            $this->markTestSkipped('Requires isolated MySQL containers on high loopback ports.');
        }

        return $port;
    }

    private function confirmedRebuild(): int
    {
        return $this->rebuild(['--confirm-database' => $this->database, '--writes-stopped' => true]);
    }

    private function rebuild(array $arguments = []): int
    {
        return $this->command('client:rebuild', $arguments + $this->options);
    }

    private function command(string $command, array $arguments): int
    {
        $output = new BufferedOutput;
        $status = Artisan::call($command, $arguments, $output);
        $this->output = $output->fetch();

        return $status;
    }
}
