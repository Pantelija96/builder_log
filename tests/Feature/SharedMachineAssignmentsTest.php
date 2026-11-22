<?php

namespace Tests\Feature;

use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedMachineAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    public static function machineTypes(): array
    {
        return [
            'excavator' => ['excavator', WorkerRole::OPERATOR, 'operator'],
            'truck' => ['truck', WorkerRole::DRIVER, 'driver'],
        ];
    }

    #[DataProvider('machineTypes')]
    public function test_shared_takeover_edit_lock_and_cleanup(string $type, WorkerRole $role, string $selfRoute): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
        $company = Company::factory()->create();
        $site = ConstructionSite::factory()->for($company)->create();
        $worker = Worker::factory()->for($company)->create(['role' => $role, 'hourly_rate' => 1400]);
        $first = Worker::factory()->for($company)->siteManager()->create();
        $second = Worker::factory()->for($company)->siteManager()->create();
        $outsider = Worker::factory()->for($company)->siteManager()->create();
        $machine = $this->machine($company, $type);
        $prefix = "/api/v1/{$type}-logs";
        $period = $this->period($machine, $worker, '08:00:00', '10:00:00');

        Sanctum::actingAs($worker);
        $selfPayload = ['machine_id' => $machine->id, 'construction_site_id' => $site->id];
        $id = $this->postJson("$prefix/$selfRoute", $selfPayload + [
            'operator_started_at' => $period['site_manager_started_at'],
        ])->assertOk()->json('data.id');
        $assignment = MachineAssignment::sole();
        $this->assertNull($assignment->site_manager_id);
        $this->assertNull($assignment->daily_log_id);
        $this->assertDatabaseCount('worker_attendances', 0);
        $this->patchJson("$prefix/$id", [
            'site_manager_started_at' => $period['site_manager_started_at'],
            'site_manager_finished_at' => $period['site_manager_finished_at'],
        ])->assertForbidden();

        $daily = DailyLog::factory()->for($company)->withManager($first)->create([
            'construction_site_id' => $site->id,
            'date' => today(),
        ]);
        $daily->siteManagers()->attach($second);

        $history = "/api/v1/workers/{$worker->id}/work-history";
        foreach ([$first, $second] as $member) {
            Sanctum::actingAs($member);
            $this->getJson('/api/v1/machine-assignments')->assertOk()->assertJsonPath('data.0.id', $assignment->id);
            $this->getJson('/api/v1/machine-assignments/current?worker_id='.$worker->id)
                ->assertOk()->assertJsonPath('data.id', $assignment->id);
            $this->getJson("$prefix/occupied")->assertOk()->assertJsonPath('data.0.id', $assignment->id);
            $this->getJson($history)->assertOk()->assertJsonPath('data.history.0.id', $id);
        }

        Sanctum::actingAs($outsider);
        $this->getJson('/api/v1/machine-assignments')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("$prefix/occupied")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($history)->assertOk()->assertJsonCount(0, 'data.history');
        $this->getJson("$prefix/available")->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("$prefix/daily-logs/{$daily->id}", $period)->assertForbidden();
        $this->patchJson("$prefix/$id", ['note_site_manager' => 'denied'])->assertForbidden();
        $this->deleteJson("$prefix/$id", ['reason' => 'denied'])->assertForbidden();

        Sanctum::actingAs($second);
        $this->postJson("$prefix/daily-logs/{$daily->id}", $period)->assertOk()->assertJsonPath('data.id', $id);
        $assignment->refresh();
        $this->assertSame($daily->id, $assignment->daily_log_id);
        $this->assertSame($second->id, $assignment->site_manager_id);
        $this->assertSame($worker->id, $assignment->created_by);
        $this->assertSame(1, MachineAssignment::count());
        $model = $type === 'truck' ? TruckLog::class : ExcavatorLog::class;
        $this->assertSame($worker->id, $model::sole()->created_by);
        $attendance = WorkerAttendance::sole();
        $this->assertSame($second->id, $attendance->site_manager_id);
        $this->assertSame($second->id, $attendance->created_by);
        $this->assertSame($assignment->id, $attendance->machine_assignment_id);
        $this->assertNull($attendance->ordinary_worker_day);
        $this->assertSame(1400.0, (float) $attendance->hourly_rate);
        $this->assertSame($second->id, AuditLog::where('event', LogEvent::MACHINE_ASSIGNMENT_TAKEN_OVER)->sole()->actor_id);

        Sanctum::actingAs($first);
        $this->getJson('/api/v1/machine-assignments')->assertOk()->assertJsonPath('data.0.site_manager.id', $second->id);
        $this->getJson('/api/v1/workers?role='.$role->value)->assertOk()->assertJsonPath('data.0.is_available', false);
        $attendance->delete();
        $this->patchJson("$prefix/$id", [
            'site_manager_started_at' => $period['site_manager_started_at'],
            'site_manager_finished_at' => today()->toDateString().' 10:30:00',
        ])->assertOk();
        $attendance->refresh();
        $this->assertFalse($attendance->trashed());
        $this->assertSame('10:30', $attendance->finished_at->format('H:i'));
        $this->assertSame($second->id, $attendance->created_by);
        $this->assertSame($second->id, $assignment->fresh()->site_manager_id);

        $daily->update(['is_locked' => true]);
        $this->getJson('/api/v1/machine-assignments')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("$prefix/daily-logs/{$daily->id}", $period)->assertInternalServerError();
        $this->patchJson("$prefix/$id", ['note_site_manager' => 'locked'])->assertInternalServerError();
        $this->deleteJson("$prefix/$id", ['reason' => 'locked'])->assertInternalServerError();
        Sanctum::actingAs($worker);
        $this->patchJson("$prefix/$id", ['note_operator' => 'locked'])->assertInternalServerError();
        $this->postJson("$prefix/$selfRoute", $selfPayload)->assertInternalServerError();
        $daily->update(['is_locked' => false]);

        Sanctum::actingAs($first);
        $this->deleteJson("$prefix/$id", ['reason' => 'test cleanup'])->assertOk();
        $this->assertSoftDeleted('machine_assignments', ['id' => $assignment->id]);
        $this->assertSoftDeleted($model::make()->getTable(), ['id' => $id]);
        $this->assertSoftDeleted('worker_attendances', ['id' => $attendance->id]);
        $this->assertSame($first->id, AuditLog::where('event', $type.'.log.deleted')->sole()->actor_id);
        $this->getJson("$prefix/available")->assertOk()->assertJsonPath('data.0.id', $machine->id);
        $this->getJson('/api/v1/workers?role='.$role->value)->assertOk()->assertJsonPath('data.0.is_available', true);
    }

    #[DataProvider('machineTypes')]
    public function test_periods_and_company_scope(string $type, WorkerRole $role, string $selfRoute): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
        $company = Company::factory()->create();
        $local = DailyLog::factory()->for($company)->create(['date' => today()]);
        $other = DailyLog::factory()->for($company)->create(['date' => today()]);
        [$first, $second, $third] = Worker::factory()->for($company)->count(3)->create(['role' => $role])->all();
        $a = $this->machine($company, $type);
        $b = $this->machine($company, $type);
        $prefix = "/api/v1/{$type}-logs";

        Sanctum::actingAs($local->siteManagers()->firstOrFail());
        $start = $this->period($a, $first, '08:00:00', '10:00:00');
        $startId = $this->postJson("$prefix/daily-logs/{$local->id}", $start)->assertOk()->json('data.id');
        $this->getJson("$prefix/available")->assertOk()->assertJsonFragment(['id' => $a->id]);

        Sanctum::actingAs($other->siteManagers()->firstOrFail());
        $url = "$prefix/daily-logs/{$other->id}";
        $this->postJson($url, $this->period($b, $first, '09:00:00', '11:00:00'))->assertInternalServerError();
        $this->postJson($url, $this->period($a, $second, '09:00:00', '11:00:00'))->assertInternalServerError();
        $this->postJson($url, $this->period($b, $first, '10:00:00', '12:00:00'))->assertOk();
        $this->postJson($url, $this->period($a, $second, '10:00:00', '12:00:00'))->assertOk();
        $this->postJson($url, $this->period($a, $second, '12:00:00', null))->assertOk();
        $this->postJson($url, $this->period($a, $third, '13:00:00', '14:00:00'))->assertInternalServerError();
        $this->assertSame(4, WorkerAttendance::count());

        $foreign = DailyLog::factory()->create(['date' => today()]);
        $foreignMachine = $this->machine($foreign->company, $type);
        $foreignWorker = Worker::factory()->for($foreign->company)->create(['role' => $role]);
        $this->postJson($url, $this->period($foreignMachine, $third, '15:00:00', '16:00:00'))->assertNotFound();
        $this->postJson($url, $this->period($b, $foreignWorker, '15:00:00', '16:00:00'))->assertNotFound();
        $this->postJson("$prefix/daily-logs/{$foreign->id}", $start)->assertNotFound();
        Sanctum::actingAs($foreign->siteManagers()->firstOrFail());
        $this->patchJson("$prefix/$startId", ['note_site_manager' => 'foreign'])->assertNotFound();
        $this->deleteJson("$prefix/$startId", ['reason' => 'foreign'])->assertNotFound();
        $this->getJson('/api/v1/machine-assignments')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/machine-assignments/current?worker_id='.$first->id)->assertNotFound();
        $this->getJson("/api/v1/workers/{$first->id}/work-history")->assertNotFound();
        $this->getJson('/api/v1/workers?role='.$role->value)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $foreignWorker->id);
        $this->getJson("$prefix/available")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $foreignMachine->id);

        $assignment = MachineAssignment::where('daily_log_id', $local->id)->sole();
        $assignment->update(['daily_log_id' => $other->id]);
        Sanctum::actingAs($other->siteManagers()->firstOrFail());
        $this->patchJson("$prefix/$startId", ['note_site_manager' => 'wrong site'])->assertNotFound();
        $this->getJson('/api/v1/machine-assignments?construction_site_id='.$local->construction_site_id)
            ->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('machineTypes')]
    public function test_legacy_pending_assignment_can_be_taken_over_once_without_a_start(string $type, WorkerRole $role, string $selfRoute): void
    {
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
        $daily = DailyLog::factory()->create(['date' => today()]);
        $worker = Worker::factory()->for($daily->company)->create(['role' => $role]);
        $manager = Worker::factory()->for($daily->company)->siteManager()->create();
        $daily->siteManagers()->attach($manager);
        $machine = $this->machine($daily->company, $type);
        $prefix = "/api/v1/{$type}-logs";

        Sanctum::actingAs($worker);
        $id = $this->postJson("$prefix/$selfRoute", [
            'construction_site_id' => $daily->construction_site_id,
            'machine_id' => $machine->id,
        ])->assertOk()->json('data.id');
        $assignment = MachineAssignment::sole();
        $this->assertNull($assignment->site_manager_id);
        $assignment->update(['site_manager_id' => $daily->siteManagers()->firstOrFail()->id]);

        $payload = ['machine_id' => $machine->id, 'worker_id' => $worker->id];
        $url = "$prefix/daily-logs/{$daily->id}";
        $admin = Worker::factory()->for($daily->company)->admin()->create();
        Sanctum::actingAs($admin);
        $this->postJson($url, $payload)->assertUnprocessable();

        Sanctum::actingAs($manager);
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame($manager->id, $assignment->fresh()->site_manager_id);
        $this->assertDatabaseCount('worker_attendances', 0);

        Sanctum::actingAs($daily->siteManagers()->firstOrFail());
        $this->postJson($url, $payload)->assertInternalServerError();
        $period = $this->period($machine, $worker, '08:00:00', '10:00:00');
        $this->patchJson("$prefix/$id", [
            'site_manager_started_at' => $period['site_manager_started_at'],
            'site_manager_finished_at' => $period['site_manager_finished_at'],
        ])->assertOk();
        $this->assertSame($manager->id, $assignment->fresh()->site_manager_id);
        $this->assertSame($daily->siteManagers()->firstOrFail()->id, WorkerAttendance::sole()->site_manager_id);
        $this->assertSame($daily->siteManagers()->firstOrFail()->id, WorkerAttendance::sole()->created_by);
    }

    private function machine(Company $company, string $type): Machine
    {
        return Machine::factory()->for($company)->create([
            'type' => $type,
            'owner_type' => 'company',
            'owner_id' => $company->id,
        ]);
    }

    private function period(Machine $machine, Worker $worker, string $start, ?string $finish): array
    {
        $date = today()->toDateString();

        return [
            'machine_id' => $machine->id,
            'worker_id' => $worker->id,
            'site_manager_started_at' => "$date $start",
            'site_manager_finished_at' => $finish === null ? null : "$date $finish",
        ];
    }
}
