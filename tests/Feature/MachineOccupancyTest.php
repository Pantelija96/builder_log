<?php

namespace Tests\Feature;

use App\Enums\WorkerRole;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\Worker;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MachineOccupancyTest extends TestCase
{
    use RefreshDatabase;

    public static function machines(): array
    {
        return [
            'excavator' => ['excavator', WorkerRole::OPERATOR],
            'truck' => ['truck', WorkerRole::DRIVER],
        ];
    }

    #[DataProvider('machines')]
    public function test_cross_midnight_period_and_rebooking(string $type, WorkerRole $role): void
    {
        [$old, $new, $machine, $first, $second] = $this->fixture($type, $role);
        $prefix = "/api/v1/{$type}-logs";
        $this->at('2026-11-23 22:00');
        $this->postJson("$prefix/daily-logs/{$old->id}", $this->period($machine, $first, '2026-11-23 22:00', '2026-11-24 00:30'))
            ->assertOk()->assertJsonPath('data.site_manager_finished_at', '2026-11-23T23:30:00.000000Z');

        $this->at('2026-11-24 00:29:59');
        $this->assertOccupancy($prefix, $machine, true);
        $this->getJson('/api/v1/machine-assignments/current?worker_id='.$first->id)
            ->assertOk()->assertJsonPath('data.machine.id', $machine->id);
        $url = "$prefix/daily-logs/{$new->id}";
        $this->postJson($url, $this->period($machine, $second, '2026-11-24 00:29', '2026-11-24 02:00'))
            ->assertInternalServerError();
        $this->at('2026-11-24 00:30');
        $this->assertOccupancy($prefix, $machine, false);
        $this->at('2026-11-24 00:31');
        $this->assertOccupancy($prefix, $machine, false);
        $id = $this->postJson($url, $this->period($machine, $second, '2026-11-24 00:31', '2026-11-24 02:00'))
            ->assertOk()->json('data.id');

        $this->patchJson("$prefix/$id", ['site_manager_started_at' => '2026-11-24T00:29:00+01:00'])
            ->assertInternalServerError();
        $this->patchJson("$prefix/$id", ['site_manager_started_at' => '2026-11-23T23:30:00Z'])
            ->assertOk()->assertJsonPath('data.site_manager_started_at', '2026-11-23T23:30:00.000000Z');
        $table = $type === 'truck' ? 'truck_logs' : 'excavator_logs';
        $this->assertDatabaseHas($table, ['id' => $id, 'site_manager_started_at' => '2026-11-24 00:30:00']);
        $this->deleteJson("$prefix/$id", ['reason' => 'Verify create boundary'])->assertOk();
        $this->at('2026-11-24 00:30');
        $this->postJson($url, $this->period($machine, $second, '2026-11-24 00:30', '2026-11-24 02:00'))->assertOk();

    }

    #[DataProvider('machines')]
    public function test_finish_without_start_is_rejected(string $type, WorkerRole $role): void
    {
        [, $new, $machine, $first, $second] = $this->fixture($type, $role);
        $prefix = "/api/v1/{$type}-logs/daily-logs/{$new->id}";
        $this->at('2026-11-24 00:31');
        $this->postJson($prefix, $this->period($machine, $first, null, '2026-11-24 00:30'))->assertUnprocessable();
        $this->postJson($prefix, $this->period($machine, $second, '2026-11-24 00:31', '2026-11-24 02:00'))->assertOk();
    }

    #[DataProvider('machines')]
    public function test_open_authoritative_interval_ends_at_business_midnight(string $type, WorkerRole $role): void
    {
        [$old, , $machine, $first] = $this->fixture($type, $role);
        $prefix = "/api/v1/{$type}-logs";
        $this->at('2026-11-23 21:59');
        $id = $this->postJson("$prefix/daily-logs/{$old->id}", $this->period($machine, $first, '2026-11-23 22:00', null))
            ->assertOk()->json('data.id');
        $this->assertOccupancy($prefix, $machine, false);
        $this->at('2026-11-23 22:00');
        $this->assertOccupancy($prefix, $machine, true);
        $this->at('2026-11-23 23:59:59');
        $this->assertOccupancy($prefix, $machine, true);
        $this->at('2026-11-24 00:00:00');
        $this->assertOccupancy($prefix, $machine, false);
        $this->patchJson("$prefix/$id", ['site_manager_finished_at' => '2026-11-23T23:30:00Z'])->assertOk();
        $this->at('2026-11-24 00:30');
        $this->assertOccupancy($prefix, $machine, false);
    }

    #[DataProvider('machines')]
    public function test_self_assignment_uses_local_midnight_not_worker_finish(string $type, WorkerRole $role): void
    {
        [, $log, $machine, $first, $second] = $this->fixture($type, $role);
        $prefix = "/api/v1/{$type}-logs";
        $this->at('2026-11-24 08:00');
        Sanctum::actingAs($first);
        $this->postJson("$prefix/{$role->value}", [
            'machine_id' => $machine->id, 'construction_site_id' => $log->construction_site_id,
            'operator_started_at' => '2026-11-24 08:00',
            'operator_finished_at' => '2026-11-24 10:00',
        ])->assertOk();

        Sanctum::actingAs($log->siteManagers()->sole());
        $this->at('2026-11-24 10:01');
        $this->assertOccupancy($prefix, $machine, true);
        $this->postJson("$prefix/daily-logs/{$log->id}", $this->period($machine, $second, '2026-11-24 11:00', '2026-11-24 12:00'))
            ->assertInternalServerError();
        $this->at('2026-11-24 23:59');
        $this->assertOccupancy($prefix, $machine, true);
        $this->at('2026-11-25 00:00');
        $this->assertOccupancy($prefix, $machine, false);
        $this->assertDatabaseCount('worker_attendances', 0);

        $next = DailyLog::factory()->withManager($log->siteManagers()->sole())->for($log->company)->create([
            'date' => '2026-11-25', 'construction_site_id' => $log->construction_site_id,
        ]);
        $this->postJson("$prefix/daily-logs/{$next->id}", $this->period($machine, $second, '2026-11-25 00:00', '2026-11-25 02:00'))->assertOk();
    }

    #[DataProvider('machines')]
    public function test_completed_night_shift_allows_self_assignment_at_0031(string $type, WorkerRole $role): void
    {
        [$old, $today, $machine, $first, $second] = $this->fixture($type, $role);
        $prefix = "/api/v1/{$type}-logs";
        $this->at('2026-11-23 22:00');
        $this->postJson("$prefix/daily-logs/{$old->id}", $this->period($machine, $first, '2026-11-23 22:00', '2026-11-24 00:30'))->assertOk();

        $this->at('2026-11-24 00:31');
        $this->assertOccupancy($prefix, $machine, false);
        Sanctum::actingAs($second);
        $this->postJson("$prefix/{$role->value}", [
            'machine_id' => $machine->id,
            'construction_site_id' => $today->construction_site_id,
            'operator_started_at' => '2026-11-24 00:31',
        ])->assertOk();
        $this->assertOccupancy($prefix, $machine, true);
        $this->assertDatabaseCount('worker_attendances', 1);
    }

    #[DataProvider('machines')]
    public function test_retroactive_machine_writes_validate_the_entered_period_across_sites(string $type, WorkerRole $role): void
    {
        [, $firstLog, $machine, $first, $second] = $this->fixture($type, $role);
        $manager = $firstLog->siteManagers()->sole();
        $secondLog = DailyLog::factory()->for($firstLog->company)->withManager($manager)->create(['date' => '2026-11-24']);
        $prefix = "/api/v1/{$type}-logs";
        $this->at('2026-11-24 18:00');

        $this->postJson("$prefix/daily-logs/{$firstLog->id}",
            $this->period($machine, $first, '2026-11-24 08:00', '2026-11-24 12:00'))->assertOk();
        $this->assertOccupancy($prefix, $machine, false);
        $url = "$prefix/daily-logs/{$secondLog->id}";
        $this->postJson($url, $this->period($machine, $second, '2026-11-24 10:00', '2026-11-24 14:00'))
            ->assertInternalServerError();
        $id = $this->postJson($url, $this->period($machine, $second, '2026-11-24 12:00', '2026-11-24 16:00'))
            ->assertOk()->json('data.id');
        $this->patchJson("$prefix/$id", ['site_manager_started_at' => '2026-11-24 11:59:00'])
            ->assertInternalServerError();
    }

    private function assertOccupancy(string $prefix, Machine $machine, bool $occupied): void
    {
        $available = $this->getJson("$prefix/available")->assertOk()->assertJsonCount($occupied ? 0 : 1, 'data');
        $reserved = $this->getJson("$prefix/occupied")->assertOk()->assertJsonCount($occupied ? 1 : 0, 'data');
        if ($occupied) {
            $reserved->assertJsonPath('data.0.machine.id', $machine->id);
        } else {
            $available->assertJsonPath('data.0.id', $machine->id);
        }
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse($time, 'Europe/Belgrade'));
    }

    private function fixture(string $type, WorkerRole $role): array
    {
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
        $old = DailyLog::factory()->create(['date' => '2026-11-23']);
        $manager = $old->siteManagers()->sole();
        $new = DailyLog::factory()->withManager($manager)->for($old->company)->create([
            'date' => '2026-11-24', 'construction_site_id' => $old->construction_site_id,
        ]);
        $machine = Machine::factory()->for($old->company)->create([
            'type' => $type, 'owner_type' => 'company', 'owner_id' => $old->company_id,
        ]);
        [$first, $second] = Worker::factory()->for($old->company)->count(2)->create(['role' => $role])->all();
        Sanctum::actingAs($manager);

        return [$old, $new, $machine, $first, $second];
    }

    private function period(Machine $machine, Worker $worker, ?string $start, ?string $end): array
    {
        return ['machine_id' => $machine->id, 'worker_id' => $worker->id,
            'site_manager_started_at' => $start, 'site_manager_finished_at' => $end];
    }
}
