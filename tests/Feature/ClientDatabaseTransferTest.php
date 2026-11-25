<?php

namespace Tests\Feature;

use App\Services\Database\ClientDatabaseConnection;
use App\Services\Database\ClientDatabaseValidator;
use Generator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class ClientDatabaseTransferTest extends TestCase
{
    private string $workspace;

    private PDO $source;

    private PDO $target;

    private string $restoreOutput;

    protected function setUp(): void
    {
        parent::setUp();

        $port = getenv('BUILDERLOG_TRANSFER_TEST_MYSQL_PORT');
        if (! $port || ! ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) {
            $this->markTestSkipped('Requires an isolated MySQL 8.4 container exposed on a loopback port.');
        }

        $admin = new PDO("mysql:host=127.0.0.1;port=$port", 'root', '', [PDO::ATTR_TIMEOUT => 2]);
        self::assertSame('builderlog_transfer_test_guard', $admin->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='builderlog_transfer_test_guard'")->fetchColumn(), 'Use the dedicated disposable test server.');
        $suffix = bin2hex(random_bytes(6));
        $sourceName = 'bl_transfer_test_source_'.$suffix;
        $targetName = 'bl_transfer_test_target_'.$suffix;
        foreach ([$sourceName, $targetName] as $database) {
            $admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $this->workspace = sys_get_temp_dir().'/builderlog-testing-'.Str::uuid();
        $common = ['host' => '127.0.0.1', 'port' => $port, 'username' => 'root', 'password' => ''];
        $source = $common + ['name' => 'legacy', 'database' => $sourceName];
        $target = $common + ['name' => 'next', 'database' => $targetName];
        config([
            'client_database.storage' => $this->workspace,
            'client_database.clients' => [
                'tester' => ['source' => $source, 'destination' => $target],
                'other' => ['source' => $source, 'destination' => $common + ['name' => 'other', 'database' => 'bl_transfer_test_other_'.$suffix]],
            ],
            'database.connections.fixture_source' => app(ClientDatabaseConnection::class)->config($source),
        ]);

        $this->source = DB::connection('fixture_source')->getPdo();
        $this->target = new PDO("mysql:host=127.0.0.1;port=$port;dbname=$targetName", 'root', '', [PDO::ATTR_STRINGIFY_FETCHES => true]);
        self::assertSame(0, Artisan::call('migrate', ['--database' => 'fixture_source', '--force' => true]));
        $this->source->exec("INSERT INTO companies (id,name) VALUES (91,'Company');
            INSERT INTO workers (id,company_id,first_name,last_name,username,password,role,created_at,deleted_at,display_order)
                VALUES (101,91,'Test','Manager','manager','test-hash','site_manager','2026-10-11 08:13:32','2026-10-18 05:21:15',9);
            INSERT INTO construction_sites (id,company_id,name,latitude,longitude)
                VALUES (71,91,'Site',45.1234567,-19.9876543);
            INSERT INTO daily_logs (id,company_id,construction_site_id,date,created_at,deleted_at)
                VALUES (41,91,71,'2026-10-12','2026-10-12 05:03:07','2026-10-14 06:15:05');
            INSERT INTO daily_log_site_managers (id,daily_log_id,site_manager_id,created_at,updated_at)
                VALUES (79,41,101,'2026-10-12 05:07:09','2026-10-12 05:09:11');
            INSERT INTO audit_logs (id,company_id,actor_id,event,auditable_type,auditable_id,old_values,new_values)
                VALUES (18446744073709550000,91,101,'test','worker_attendance',999,NULL,'null');
            ALTER TABLE workers AUTO_INCREMENT=700");
    }

    protected function tearDown(): void
    {
        if (isset($this->workspace)) {
            File::deleteDirectory($this->workspace);
        }
        parent::tearDown();
    }

    public function test_legacy_snapshot_restores_values_defaults_and_historical_exceptions(): void
    {
        $this->source->exec('ALTER TABLE workers DROP COLUMN display_order;
            ALTER TABLE daily_logs ADD COLUMN site_manager_id BIGINT UNSIGNED NULL;
            UPDATE daily_logs SET site_manager_id=101;
            ALTER TABLE daily_logs MODIFY COLUMN site_manager_id BIGINT UNSIGNED NOT NULL,
                ADD CONSTRAINT daily_logs_site_manager_id_foreign FOREIGN KEY (site_manager_id)
                REFERENCES workers(id) ON UPDATE CASCADE ON DELETE RESTRICT;
            DROP TABLE daily_log_site_managers');
        $default = DB::getDefaultConnection();
        $id = $this->export();

        self::assertSame(0, $this->restore($id, ['--dry-run' => true]));
        self::assertStringContainsString('Preflight passed', $this->restoreOutput);
        self::assertFalse($this->target->query('SHOW FULL TABLES')->fetch());
        self::assertSame(0, $this->restore($id), $this->restoreOutput);
        self::assertSame($default, DB::getDefaultConnection());

        $worker = $this->target->query('SELECT id,company_id,password,created_at,deleted_at,display_order FROM workers')->fetch(PDO::FETCH_NUM);
        self::assertSame(['101', '91', 'test-hash', '2026-10-11 08:13:32', '2026-10-18 05:21:15', '0'], $worker);
        self::assertSame('700', $this->target->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workers'")->fetchColumn());
        self::assertSame(['41', '101', '2026-10-12 05:03:07', '2026-10-12 05:03:07'], $this->target->query('SELECT daily_log_id,site_manager_id,created_at,updated_at FROM daily_log_site_managers')->fetch(PDO::FETCH_NUM));
        self::assertSame(['45.1234567', '-19.9876543'], $this->target->query('SELECT latitude,longitude FROM construction_sites WHERE id=71')->fetch(PDO::FETCH_NUM));
        self::assertSame(['18446744073709550000', '999', null, 'null'], $this->target->query('SELECT id,auditable_id,old_values,new_values FROM audit_logs')->fetch(PDO::FETCH_NUM));
        self::assertSame('50', $this->target->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        self::assertSame('101', $this->source->query('SELECT site_manager_id FROM daily_logs')->fetchColumn());
    }

    public function test_mixed_source_retains_pivot_ids_and_never_adds_duplicate_memberships(): void
    {
        $this->source->exec("ALTER TABLE daily_logs ADD COLUMN site_manager_id BIGINT UNSIGNED NULL;
            UPDATE daily_logs SET site_manager_id=101;
            ALTER TABLE daily_logs MODIFY COLUMN site_manager_id BIGINT UNSIGNED NOT NULL,
                ADD CONSTRAINT daily_logs_site_manager_id_foreign FOREIGN KEY (site_manager_id)
                REFERENCES workers(id) ON UPDATE CASCADE ON DELETE RESTRICT;
            INSERT INTO daily_logs (id,company_id,construction_site_id,date,created_at,site_manager_id)
                VALUES (42,91,71,'2026-10-13','2026-10-13 05:05:05',101)");
        $id = $this->export();
        self::assertSame(0, $this->restore($id), $this->restoreOutput);
        self::assertSame([
            ['79', '41', '101', '2026-10-12 05:07:09', '2026-10-12 05:09:11'],
            ['80', '42', '101', '2026-10-13 05:05:05', '2026-10-13 05:05:05'],
        ], $this->target->query('SELECT id,daily_log_id,site_manager_id,created_at,updated_at FROM daily_log_site_managers ORDER BY id')->fetchAll(PDO::FETCH_NUM));
        self::assertSame('9', $this->target->query('SELECT display_order FROM workers')->fetchColumn());
    }

    public function test_corruption_identity_nonempty_and_unexpected_schema_are_rejected(): void
    {
        $id = $this->export();
        self::assertSame(1, $this->restore($id, ['--destination' => 'unmapped']));
        self::assertStringContainsString('mapped', $this->restoreOutput);
        self::assertSame(1, $this->restore($id, ['--client' => 'other', '--destination' => 'other']));
        $path = $this->workspace."/tester/$id";
        $other = $this->workspace."/other/$id";
        File::copyDirectory($path, $other);
        self::assertSame(1, $this->restore($id, ['--client' => 'other', '--destination' => 'other']));
        self::assertStringContainsString('Invalid, incomplete or incompatible', $this->restoreOutput);

        $manifest = file_get_contents($path.'/manifest.json');
        $modified = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
        $modified['complete'] = false;
        file_put_contents($path.'/manifest.json', json_encode($modified, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->restore($id));
        file_put_contents($path.'/manifest.json', $manifest);

        $worker = file_get_contents($path.'/workers.jsonl');
        file_put_contents($path.'/workers.jsonl', $worker.'invalid'."\n");
        self::assertSame(1, $this->restore($id));
        self::assertStringContainsString('corrupt export file', $this->restoreOutput);
        file_put_contents($path.'/workers.jsonl', $worker);

        $original = config('client_database.clients.tester.destination.database');
        config(['client_database.clients.tester.destination.database' => config('client_database.clients.tester.source.database')]);
        self::assertSame(1, $this->restore($id));
        self::assertStringContainsString('mapped to a different transfer role', $this->restoreOutput);
        config(['client_database.clients.tester.destination.database' => $original]);

        $this->target->exec('CREATE VIEW already_here AS SELECT 1 AS n');
        self::assertSame(1, $this->restore($id, ['--dry-run' => true]));
        self::assertStringContainsString('Destination is not empty', $this->restoreOutput);
        self::assertSame('1', $this->target->query('SELECT n FROM already_here')->fetchColumn());

        $this->source->exec('ALTER TABLE workers ADD COLUMN unrelated VARCHAR(100)');
        self::assertSame(1, Artisan::call('client:export', ['--client' => 'tester', '--source' => 'legacy']));
        self::assertStringContainsString('Incompatible columns in workers', Artisan::output());
    }

    public function test_import_failure_leaves_schema_but_rolls_back_data_and_sanitizes_output(): void
    {
        $id = $this->export();
        $validator = app(ClientDatabaseValidator::class);
        $reads = 0;
        $this->partialMock(ClientDatabaseValidator::class)
            ->shouldReceive('rowsFromFile')
            ->andReturnUsing(function (string $path, string $table, array $columns) use ($validator, &$reads): Generator {
                if ($table === 'construction_sites' && ++$reads === 3) {
                    // Preflight has read the file twice. Fail during the transaction after earlier inserts.
                    throw new RuntimeException('Simulated import read failure.');
                }

                return $validator->rowsFromFile($path, $table, $columns);
            });

        self::assertSame(1, $this->restore($id));
        self::assertStringContainsString('Failure at import construction_sites', $this->restoreOutput);
        self::assertStringNotContainsString('test-hash', $this->restoreOutput);
        self::assertStringNotContainsString('INSERT INTO', $this->restoreOutput);
        self::assertSame('50', $this->target->query('SELECT COUNT(*) FROM migrations')->fetchColumn());
        self::assertSame('0', $this->target->query('SELECT COUNT(*) FROM companies')->fetchColumn());
        self::assertSame('0', $this->target->query('SELECT COUNT(*) FROM workers')->fetchColumn());
    }

    public function test_nonempty_jobs_require_an_explicit_archive_policy(): void
    {
        $this->source->exec("INSERT INTO jobs (queue,payload,attempts,available_at,created_at) VALUES ('default','private job',0,1,1)");
        $options = ['--client' => 'tester', '--source' => 'legacy'];
        self::assertSame(1, Artisan::call('client:export', $options));
        self::assertStringContainsString('explicit --operational=archive', Artisan::output());
        self::assertSame(0, Artisan::call('client:export', $options + ['--operational' => 'archive']), Artisan::output());
        $manifests = glob($this->workspace.'/tester/*/manifest.json');
        self::assertCount(1, $manifests);
        self::assertFileExists(dirname($manifests[0]).'/jobs.jsonl');
        self::assertSame(0, $this->restore(basename(dirname($manifests[0]))), $this->restoreOutput);
        self::assertSame('0', $this->target->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
        self::assertSame('1', $this->source->query('SELECT COUNT(*) FROM jobs')->fetchColumn());
    }

    private function export(): string
    {
        self::assertSame(0, Artisan::call('client:export', ['--client' => 'tester', '--source' => 'legacy']), Artisan::output());
        $manifests = glob($this->workspace.'/tester/*/manifest.json');
        self::assertCount(1, $manifests);

        return basename(dirname($manifests[0]));
    }

    private function restore(string $id, array $options = []): int
    {
        $output = new BufferedOutput;
        $status = Artisan::call('client:restore', $options + [
            '--client' => 'tester', '--export' => $id, '--destination' => 'next',
        ], $output);
        $this->restoreOutput = $output->fetch();

        return $status;
    }
}
