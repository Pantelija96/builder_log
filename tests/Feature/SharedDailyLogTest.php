<?php

namespace Tests\Feature;

use App\Enums\LogEvent;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharedDailyLogTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);

        DB::purge('sqlite');
    }

    public function test_managers_can_select_multiple_sites_and_share_one_daily_log(): void
    {
        $company = Company::factory()->create();
        $a = Worker::factory()->for($company)->siteManager()->create();
        $b = Worker::factory()->for($company)->siteManager()->create();
        $other = Worker::factory()->for($company)->siteManager()->create();
        $first = ConstructionSite::factory()->for($company)->create();
        $second = ConstructionSite::factory()->for($company)->create();
        $first->siteManagers()->attach([$a->id, $b->id]);
        $second->siteManagers()->attach($a);

        Sanctum::actingAs($a);
        $firstResponse = $this->postJson("/api/v1/construction-sites/{$first->id}/select")
            ->assertCreated()
            ->assertJsonMissingPath('data.site_manager_id')
            ->assertJsonMissingPath('data.site_manager')
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.site_managers.0.id', $a->id);
        $firstId = $firstResponse->json('data.id');
        self::assertSame(today()->toDateString(), DailyLog::findOrFail($firstId)->date->toDateString());
        $joinedAt = $firstResponse->json('data.site_managers.0.joined_at');
        self::assertNotNull($joinedAt);
        $secondId = $this->postJson("/api/v1/construction-sites/{$second->id}/select")
            ->assertCreated()->json('data.id');
        self::assertNotSame($firstId, $secondId);

        Sanctum::actingAs($b);
        $this->getJson('/api/v1/construction-sites')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.can_select', true);
        $this->getJson("/api/v1/daily-logs/$firstId")->assertNotFound();
        $this->postJson("/api/v1/construction-sites/{$first->id}/select")
            ->assertOk()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonMissingPath('data.site_manager_id')
            ->assertJsonPath('data.site_managers.1.id', $b->id);
        $this->getJson('/api/v1/daily-logs')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $firstId);
        $this->getJson("/api/v1/daily-logs/$firstId")->assertOk()->assertJsonCount(2, 'data.site_managers');
        $this->getJson("/api/v1/daily-logs/$secondId")->assertNotFound();

        Sanctum::actingAs($a);
        $this->travel(1)->minute();
        $this->postJson("/api/v1/construction-sites/{$first->id}/select")->assertOk()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('data.site_managers.0.joined_at', $joinedAt);
        $this->getJson('/api/v1/daily-logs')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/daily-logs?site_manager_id={$b->id}")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $firstId);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/construction-sites/{$first->id}/select")->assertForbidden();
        $this->getJson("/api/v1/daily-logs?site_manager_id={$a->id}")->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('daily_logs', 2);
        $this->assertDatabaseCount('daily_log_site_managers', 3);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_company_and_role_are_enforced_for_selection_and_daily_log_reads(): void
    {
        $log = DailyLog::factory()->create();
        $site = $log->constructionSite;
        $foreign = Worker::factory()->siteManager()->create();
        $admin = Worker::factory()->for($log->company)->admin()->create();
        $inactive = Worker::factory()->for($log->company)->siteManager()->create(['is_active' => false]);
        $site->siteManagers()->attach([$foreign->id, $inactive->id]);

        Sanctum::actingAs($foreign);
        $this->postJson("/api/v1/construction-sites/{$site->id}/select")->assertNotFound();
        $this->getJson("/api/v1/daily-logs/{$log->id}")->assertNotFound();
        $this->getJson('/api/v1/daily-logs')->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($inactive);
        $this->postJson("/api/v1/construction-sites/{$site->id}/select")->assertForbidden();
        $this->getJson('/api/v1/construction-sites')->assertOk()->assertJsonPath('data.0.can_select', false);

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/construction-sites/{$site->id}/select")->assertForbidden();
        $this->getJson("/api/v1/daily-logs/{$log->id}")->assertOk();
    }

    public function test_a_non_primary_member_can_lock_and_a_new_member_can_select_the_locked_log(): void
    {
        config(['app.debug' => false]);
        $log = DailyLog::factory()->create(['date' => today()->toDateString()]);
        [$member, $newMember] = Worker::factory()->for($log->company)->siteManager()->count(2)->create()->all();
        $site = $log->constructionSite;
        $site->siteManagers()->attach([$member->id, $newMember->id]);
        $selection = "/api/v1/construction-sites/{$site->id}/select";

        Sanctum::actingAs($member);
        $this->postJson($selection)->assertOk();
        $this->postJson("/api/v1/daily-logs/{$log->id}/lock")->assertOk()
            ->assertJsonPath('data.locked_by_id', $member->id);
        $this->postJson("/api/v1/daily-logs/{$log->id}/lock")->assertStatus(500);

        Sanctum::actingAs($newMember);
        $this->getJson('/api/v1/construction-sites')->assertOk()->assertJsonPath('data.0.can_select', true);
        $this->postJson($selection)->assertOk()
            ->assertJsonPath('data.is_locked', true)->assertJsonCount(3, 'data.site_managers');
        $this->getJson("/api/v1/daily-logs/{$log->id}")->assertOk();

        Sanctum::actingAs($log->siteManagers()->firstOrFail());
        $worker = Worker::factory()->for($log->company)->create();
        $this->postJson("/api/v1/daily-logs/{$log->id}/worker-attendances", [
            'worker_id' => $worker->id, 'started_at' => today()->addHours(8)->toDateTimeString(),
        ])->assertStatus(500);
        $this->assertDatabaseCount('worker_attendances', 0);
        $this->assertDatabaseHas('activity_logs', ['event' => LogEvent::DAILY_LOG_LOCKED->value]);
        $this->assertDatabaseCount('activity_logs', 1);
    }

    public function test_legacy_post_uses_authenticated_manager_and_keeps_retroactive_creation(): void
    {
        config(['app.debug' => false]);
        $manager = Worker::factory()->siteManager()->create();
        $other = Worker::factory()->for($manager->company)->siteManager()->create();
        $site = ConstructionSite::factory()->for($manager->company)->create();
        $site->siteManagers()->attach([$manager->id, $other->id]);
        $payload = [
            'company_id' => $manager->company_id,
            'construction_site_id' => $site->id,
            'site_manager_id' => $other->id,
            'date' => today()->toDateString(),
        ];

        Sanctum::actingAs($manager);
        $id = $this->postJson('/api/v1/daily-logs', $payload)->assertCreated()
            ->assertJsonMissingPath('data.site_manager_id')
            ->assertJsonCount(1, 'data.site_managers')->json('data.id');

        Sanctum::actingAs($other);
        config(['filesystems.attachments.disk' => 'm1-legacy-uploads']);
        Storage::fake('m1-legacy-uploads');
        $this->post('/api/v1/daily-logs', $payload + [
            'attachments' => [UploadedFile::fake()->create('legacy.pdf')],
        ], ['Accept' => 'application/json'])->assertStatus(500);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('daily_log_site_managers', 1);

        $this->postJson('/api/v1/daily-logs', $payload)->assertCreated()
            ->assertJsonPath('data.id', $id)
            ->assertJsonMissingPath('data.site_manager_id')
            ->assertJsonCount(2, 'data.site_managers');

        $payload['date'] = today()->subDay()->toDateString();
        $historicalId = $this->postJson('/api/v1/daily-logs', $payload)->assertCreated()
            ->assertJsonPath('data.site_managers.0.id', $other->id)->json('data.id');
        self::assertNotSame($id, $historicalId);
        $this->postJson('/api/v1/daily-logs', $payload)->assertStatus(500);
        $this->assertDatabaseCount('activity_logs', 2);
    }

    public function test_removed_permanent_assignment_does_not_remove_historical_membership(): void
    {
        $log = DailyLog::factory()->create(['date' => today()->subDay()->toDateString()]);
        $manager = $log->siteManagers()->firstOrFail();
        $site = $log->constructionSite;
        $admin = Worker::factory()->for($log->company)->admin()->create();
        $site->siteManagers()->attach($manager);

        Sanctum::actingAs($admin);
        $this->deleteJson("/api/v1/construction-sites/{$site->id}/site-managers/{$manager->id}")->assertNoContent();

        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/daily-logs/{$log->id}")->assertOk()
            ->assertJsonPath('data.site_managers.0.id', $manager->id);
        $this->postJson("/api/v1/construction-sites/{$site->id}/select")->assertForbidden();
        $manager->delete();

        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/daily-logs/{$log->id}")->assertOk()
            ->assertJsonPath('data.site_managers.0.id', $manager->id);
    }

    public function test_soft_deleted_daily_log_keeps_the_unique_site_date(): void
    {
        $log = DailyLog::factory()->create(['date' => today()->toDateString()]);
        $log->constructionSite->siteManagers()->attach($log->siteManagers()->firstOrFail()->id);
        Sanctum::actingAs($log->siteManagers()->firstOrFail());
        $log->delete();

        $this->postJson("/api/v1/construction-sites/{$log->construction_site_id}/select")->assertConflict();
        $this->assertDatabaseCount('daily_logs', 1);
        $this->assertDatabaseCount('daily_log_site_managers', 1);
    }

    public function test_backfill_is_repeatable_and_includes_soft_deleted_logs(): void
    {
        $log = DailyLog::factory()->create(['created_at' => '2024-01-01 08:00:00']);
        $deleted = DailyLog::factory()->create(['created_at' => '2024-02-01 08:00:00']);
        $other = Worker::factory()->for($log->company)->siteManager()->create();
        $log->constructionSite->siteManagers()->attach($other);
        $deleted->delete();
        Schema::table('daily_logs', function ($table) {
            $table->unsignedBigInteger('site_manager_id')->nullable();
        });
        foreach ([$log, $deleted] as $record) {
            $manager = $record->siteManagers()->firstOrFail();
            $record->setAttribute('legacy_manager', $manager->id);
            DB::table('daily_logs')->where('id', $record->id)->update(['site_manager_id' => $manager->id]);
        }
        DB::table('daily_log_site_managers')->delete();

        $migration = require database_path('migrations/2026_11_22_120000_create_daily_log_site_managers_table.php');
        $migration->up();
        $entries = DB::table('daily_log_site_managers')->orderBy('daily_log_id')->get()->toArray();
        $migration->up();
        self::assertEquals($entries, DB::table('daily_log_site_managers')->orderBy('daily_log_id')->get()->toArray());
        $this->assertDatabaseCount('daily_log_site_managers', 2);
        foreach ([$log, $deleted] as $record) {
            $this->assertDatabaseHas('daily_log_site_managers', [
                'daily_log_id' => $record->id,
                'site_manager_id' => $record->legacy_manager,
                'created_at' => $record->created_at->toDateTimeString(),
            ]);
        }
        $this->assertDatabaseMissing('daily_log_site_managers', ['site_manager_id' => $other->id]);
    }
}
