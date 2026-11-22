<?php

namespace Tests\Feature;

use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerAttendanceContractTest extends TestCase
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

    public function test_create_patch_and_delete_keep_attendance_and_audit_semantics(): void
    {
        config(['app.debug' => false]);
        $log = DailyLog::factory()->create(['date' => '2026-01-15']);
        $worker = Worker::factory()->for($log->company)->create(['hourly_rate' => 125.5, 'is_available' => true]);
        Sanctum::actingAs($log->siteManagers()->firstOrFail());

        $url = "/api/v1/daily-logs/{$log->id}/worker-attendances";
        $payload = [
            'worker_id' => $worker->id,
            'started_at' => '2026-01-15 08:00:00',
            'finished_at' => '2026-01-15 16:00:00',
            'advance_payment' => 20,
        ];
        $created = $this->postJson($url, $payload)->assertOk()
            ->assertJsonPath('data.worker.id', $worker->id)
            ->assertJsonPath('data.worker.is_available', false)
            ->assertJsonPath('data.creator.id', $log->siteManagers()->firstOrFail()->id)
            ->assertJsonPath('data.hourly_rate', '125.50')
            ->assertJsonPath('data.machine', null);
        $id = $created->json('data.id');
        $this->assertFalse($worker->fresh()->is_available);
        $worker->update(['hourly_rate' => 200]);

        $this->postJson($url, $payload)->assertStatus(500);
        $this->patchJson("$url/$id", ['started_at' => '2026-01-16 08:00:00'])->assertStatus(500);
        $this->patchJson("$url/$id", ['advance_payment' => false])
            ->assertUnprocessable()->assertJsonValidationErrors('advance_payment');
        $this->assertDatabaseCount('worker_attendances', 1);
        $this->assertDatabaseCount('activity_logs', 1);

        $finishedAt = $created->json('data.finished_at');
        $this->patchJson("$url/$id", [])->assertOk()
            ->assertJsonPath('data.finished_at', $finishedAt)
            ->assertJsonPath('data.advance_payment', '20.00')
            ->assertJsonMissingPath('data.worker');
        $this->patchJson("$url/$id", ['finished_at' => null, 'advance_payment' => null])->assertOk()
            ->assertJsonPath('data.finished_at', $finishedAt)
            ->assertJsonPath('data.advance_payment', '20.00');
        $this->patchJson("$url/$id", ['advance_payment' => 0])->assertOk()
            ->assertJsonPath('data.advance_payment', '0.00')
            ->assertJsonPath('data.hourly_rate', '125.50');

        $this->assertDatabaseCount('activity_logs', 4);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->deleteJson("$url/$id", ['reason' => 'Duplicate shift'])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Worker attendance deleted successfully.', 'data' => null]);
        $this->assertDatabaseMissing('worker_attendances', ['id' => $id]);
        $this->assertTrue($worker->fresh()->is_available);
        $this->assertDatabaseCount('activity_logs', 5);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'event' => LogEvent::WORKER_ATTENDANCE_DELETED->value,
            'actor_id' => $log->siteManagers()->firstOrFail()->id,
            'reason' => 'Duplicate shift',
        ]);
    }

    public function test_machine_linked_attendance_keeps_its_relation_and_duplicate_check(): void
    {
        config(['app.debug' => false]);
        $log = DailyLog::factory()->create(['date' => '2026-01-15']);
        $worker = Worker::factory()->operator()->for($log->company)->create();
        $machine = Machine::factory()->for($log->company)->create([
            'owner_type' => 'company', 'owner_id' => $log->company_id,
        ]);
        $attributes = [
            'company_id' => $log->company_id,
            'construction_site_id' => $log->construction_site_id,
            'daily_log_id' => $log->id,
            'site_manager_id' => $log->siteManagers()->firstOrFail()->id,
            'worker_id' => $worker->id,
            'created_by' => $log->siteManagers()->firstOrFail()->id,
            'date' => '2026-01-15',
        ];
        $assignment = MachineAssignment::create($attributes + ['machine_id' => $machine->id]);
        $attendance = WorkerAttendance::create($attributes + [
            'machine_assignment_id' => $assignment->id,
            'started_at' => '2026-01-15 08:00:00',
        ]);
        Sanctum::actingAs($log->siteManagers()->firstOrFail());

        $url = "/api/v1/daily-logs/{$log->id}/worker-attendances";
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.0.id', $attendance->id)
            ->assertJsonPath('data.0.machine_assignment_id', $assignment->id)
            ->assertJsonPath('data.0.machine.id', $machine->id);
        $this->postJson($url, ['worker_id' => $worker->id, 'started_at' => '2026-01-15 12:00:00'])
            ->assertStatus(500);
        $this->assertDatabaseCount('worker_attendances', 1);
    }
}
