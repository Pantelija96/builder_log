<?php

namespace Tests\Feature;

use App\Actions\DailyLog\LockDailyLogAction;
use App\Actions\DailyLog\UnlockDailyLogAction;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyLogLockContractTest extends TestCase
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

    public function test_owner_can_lock_but_only_admin_can_unlock_over_http(): void
    {
        config(['app.debug' => false]);
        $log = DailyLog::factory()->create();
        $other = Worker::factory()->siteManager()->for($log->company)->create();
        $admin = Worker::factory()->admin()->for($log->company)->create();
        $url = "/api/v1/daily-logs/{$log->id}";

        Sanctum::actingAs($other);
        $this->postJson("$url/lock")->assertStatus(500);
        $this->assertFalse($log->fresh()->is_locked);
        $this->assertDatabaseCount('activity_logs', 0);

        Sanctum::actingAs($log->siteManagers()->firstOrFail());
        $this->postJson("$url/lock")->assertOk()
            ->assertJsonPath('data.is_locked', true)
            ->assertJsonPath('data.locked_by_id', $log->siteManagers()->firstOrFail()->id);
        $this->postJson("$url/lock")->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
        $this->postJson("$url/unlock", ['reason' => 'Review'])->assertStatus(500);
        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseCount('audit_logs', 0);

        Sanctum::actingAs($admin);
        $this->postJson("$url/unlock")->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("$url/unlock", ['reason' => 'Review'])->assertOk()
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.locked_by_id', null)
            ->assertJsonPath('data.locked_at', null);
        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => LogEvent::DAILY_LOG_UNLOCKED->value,
            'reason' => 'Review',
        ]);
        $this->assertDatabaseCount('audit_logs', 1);

        $this->postJson("$url/lock")->assertOk()->assertJsonPath('data.locked_by_id', $admin->id);
        $this->assertDatabaseCount('activity_logs', 3);
    }

    public function test_system_can_lock_and_unlock_without_an_actor(): void
    {
        $log = DailyLog::factory()->create();

        $locked = app(LockDailyLogAction::class)->execute($log);
        $this->assertTrue($locked->is_locked);
        $this->assertNull($locked->locked_by);
        $unlocked = app(UnlockDailyLogAction::class)->execute($locked, reason: null);
        $this->assertFalse($unlocked->is_locked);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => null, 'event' => LogEvent::DAILY_LOG_LOCKED->value]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => null, 'event' => LogEvent::DAILY_LOG_UNLOCKED->value]);
        $this->assertDatabaseCount('activity_logs', 2);
        $this->assertDatabaseCount('audit_logs', 1);
    }
}
