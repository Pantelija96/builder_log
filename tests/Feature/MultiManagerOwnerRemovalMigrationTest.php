<?php

namespace Tests\Feature;

use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\DailyLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class MultiManagerOwnerRemovalMigrationTest extends TestCase
{
    public function test_removal_checks_backfill_and_preserves_existing_rows(): void
    {
        $port = getenv('BUILDERLOG_M4_MYSQL_PORT');
        $connection = $port === false ? 'sqlite' : 'mysql';
        config([
            'database.default' => $connection,
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'database.connections.mysql' => [
                'driver' => 'mysql', 'url' => null, 'host' => '127.0.0.1', 'port' => $port,
                'database' => 'builderlog_m4_test_core', 'username' => 'root', 'password' => '',
                'unix_socket' => '',
            ] + config('database.connections.mysql'),
        ]);
        DB::purge($connection);

        try {
            $migration = 'database/migrations/2026_11_22_140000_remove_daily_log_site_manager_id.php';
            $paths = array_map(fn ($path) => 'database/migrations/'.basename($path), glob(database_path('migrations/*.php')));
            $this->artisan('migrate:fresh', ['--path' => array_values(array_diff($paths, [$migration])), '--force' => true])->assertSuccessful();

            $owner = Worker::factory()->siteManager()->create();
            $member = Worker::factory()->for($owner->company)->siteManager()->create();
            $site = ConstructionSite::factory()->for($owner->company)->create();
            $logId = DB::table('daily_logs')->insertGetId([
                'company_id' => $owner->company_id, 'construction_site_id' => $site->id,
                'site_manager_id' => $owner->id, 'date' => '2026-02-10',
            ]);
            $daily = DailyLog::findOrFail($logId);
            $daily->siteManagers()->attach($member);
            $expense = Expense::create([
                'company_id' => $owner->company_id, 'construction_site_id' => $site->id,
                'daily_log_id' => $logId, 'date' => '2026-02-10', 'site_manager_id' => $owner->id,
                'created_by' => $owner->id, 'title' => 'Original attribution', 'amount' => 20,
            ]);
            $beforeExpense = $expense->refresh()->getRawOriginal();

            try {
                (require base_path($migration))->up();
                $this->fail('Migration must refuse to remove a legacy manager without a matching pivot row.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('verify missing legacy memberships', $exception->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('daily_logs', 'site_manager_id'));

            $daily->siteManagers()->attach($owner);
            $foreignManager = Worker::factory()->siteManager()->create();
            $daily->siteManagers()->attach($foreignManager);
            try {
                (require base_path($migration))->up();
                $this->fail('Migration must refuse a cross-company membership.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('company mismatches', $exception->getMessage());
            }
            $this->assertTrue(Schema::hasColumn('daily_logs', 'site_manager_id'));
            $daily->siteManagers()->detach($foreignManager);

            $beforeMembers = DB::table('daily_log_site_managers')->orderBy('id')->get()->toArray();
            $beforeDaily = $daily->getRawOriginal();
            unset($beforeDaily['site_manager_id']);

            $this->artisan('migrate', ['--path' => [$migration], '--force' => true])->assertSuccessful();
            $this->assertFalse(Schema::hasColumn('daily_logs', 'site_manager_id'));
            $this->assertSame($beforeDaily, $daily->refresh()->getRawOriginal());
            $this->assertEquals($beforeMembers, DB::table('daily_log_site_managers')->orderBy('id')->get()->toArray());
            $this->assertSame($beforeExpense, $expense->refresh()->getRawOriginal());
            $this->assertCount(2, app(DailyLogService::class)->findById($logId, $member)->siteManagers);
        } finally {
            DB::purge($connection);
        }
    }
}
