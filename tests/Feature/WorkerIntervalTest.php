<?php

namespace Tests\Feature;

use App\Enums\WorkerRole;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkerIntervalTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-11-24';

    public function test_available_create_update_and_delete_share_interval_truth(): void
    {
        [$a, $b, $manager, $worker] = $this->fixture();
        $foreign = Worker::factory()->create();
        $inactive = Worker::factory()->for($manager->company)->create(['is_active' => false]);
        $firstUrl = $this->attendanceUrl($a);
        $secondUrl = $this->attendanceUrl($b);
        $id = $this->postJson($firstUrl, $this->period($worker, '08:00', '12:00'))->assertOk()->json('data.id');

        // This persisted flag is deliberately stale for the requested interval.
        $worker->update(['is_available' => false]);
        $this->getJson($this->availableUrl('11:59', '16:00'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->availableUrl('12:00', '16:00'))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $worker->id)->assertJsonPath('data.0.is_available', true)
            ->assertJsonMissing(['id' => $foreign->id])->assertJsonMissing(['id' => $inactive->id]);
        $this->getJson('/api/v1/workers/available')->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($b->siteManagers()->sole());
        $this->postJson($secondUrl, $this->period($worker, '11:59', '16:00'))->assertConflict();
        $this->postJson($secondUrl, $this->period($worker, '12:00', '16:00'))->assertOk();
        Sanctum::actingAs($manager);
        $this->patchJson("$firstUrl/$id", ['finished_at' => self::DAY.'T13:00:00+01:00'])->assertConflict();
        $this->patchJson("$firstUrl/$id", ['finished_at' => null])->assertConflict();
        $this->assertSame('12:00', WorkerAttendance::findOrFail($id)->finished_at->format('H:i'));

        $this->travelTo(Carbon::parse(self::DAY.' 13:00', 'Europe/Belgrade'));
        $this->deleteJson("$firstUrl/$id", ['reason' => 'Correct duplicate entry'])->assertOk();
        $this->assertFalse($worker->fresh()->is_available);
        $this->getJson($this->availableUrl('13:00', '15:00'))->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseCount('worker_attendances', 1);
    }

    public function test_default_and_explicit_point_queries_ignore_later_periods(): void
    {
        [$a, $b, , $worker] = $this->fixture();
        $this->postJson($this->attendanceUrl($a), $this->period($worker, '08:00', '12:00'))->assertOk();
        Sanctum::actingAs($b->siteManagers()->sole());
        $this->postJson($this->attendanceUrl($b), $this->period($worker, '16:00', '20:00'))->assertOk();

        foreach (['11:00' => false, '12:00' => true, '14:00' => true, '17:00' => false] as $time => $available) {
            $this->travelTo(Carbon::parse(self::DAY." $time", 'Europe/Belgrade'));
            $expected = $available ? [$worker->id] : [];
            $default = $this->getJson('/api/v1/workers/available')->assertOk()->json('data');
            $this->assertSame($expected, array_column($default, 'id'));
            $explicit = $this->getJson($this->pointUrl(self::DAY."T$time:00+01:00"))->assertOk()->json('data');
            $this->assertSame($default, $explicit);
        }

        foreach (['11:59' => false, '13:00' => true, '16:00' => false, '20:00' => true] as $time => $available) {
            $point = $this->getJson($this->pointUrl(self::DAY."T$time:00+01:00"))->assertOk()->json('data');
            $this->assertSame($available ? [$worker->id] : [], array_column($point, 'id'));
        }

        $this->getJson($this->availableUrl('12:00', '16:00'))->assertOk()->assertJsonPath('data.0.id', $worker->id);
        $this->getJson($this->availableUrl('11:30', '16:00'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->availableUrl('12:00', null))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_incompatible_or_empty_time_parameters_are_rejected(): void
    {
        $this->fixture();
        $time = self::DAY.'T12:00:00+01:00';
        foreach ([
            ['at' => $time, 'started_at' => $time],
            ['at' => $time, 'finished_at' => $time],
            ['finished_at' => $time],
            ['at' => ''],
            ['started_at' => ''],
        ] as $query) {
            $this->getJson('/api/v1/workers/available?'.http_build_query($query))->assertUnprocessable();
        }
    }

    public static function attendanceRoles(): array
    {
        return [[WorkerRole::WORKER], [WorkerRole::OPERATOR], [WorkerRole::DRIVER]];
    }

    #[DataProvider('attendanceRoles')]
    public function test_retroactive_attendance_uses_entered_time_for_every_role(WorkerRole $role): void
    {
        [$a, $b, $manager, $worker] = $this->fixture($role);
        $this->travelTo(Carbon::parse(self::DAY.' 18:00', 'Europe/Belgrade'));
        $first = $this->attendanceUrl($a);
        $second = $this->attendanceUrl($b);
        $id = $this->postJson($first, $this->period($worker, '08:00', '12:00'))->assertOk()->json('data.id');

        Sanctum::actingAs($b->siteManagers()->sole());
        $this->postJson($second, $this->period($worker, '10:00', '14:00'))->assertConflict();
        $this->postJson($second, $this->period($worker, '09:00', '10:00'))->assertConflict();
        $this->postJson($second, $this->period($worker, '12:00', '16:00'))->assertOk();

        Sanctum::actingAs($manager);
        $this->patchJson("$first/$id", ['finished_at' => self::DAY.'T13:00:00+01:00'])->assertConflict();
    }

    public function test_open_attendance_blocks_until_local_midnight_and_allows_later_dates(): void
    {
        [$a, $b, $manager, $worker] = $this->fixture();
        $third = DailyLog::factory()->for($manager->company)->withManager($manager)->create(['date' => self::DAY]);
        $this->postJson($this->attendanceUrl($a), $this->period($worker, '08:00', '12:00'))->assertOk();

        Sanctum::actingAs($b->siteManagers()->sole());
        $this->postJson($this->attendanceUrl($b), $this->period($worker, '12:00', null))->assertOk();
        Sanctum::actingAs($manager);
        $this->postJson($this->attendanceUrl($third), $this->period($worker, '18:00', '20:00'))->assertConflict();
        $this->getJson($this->availableUrl('23:59', null))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->pointUrl(self::DAY.'T23:59:59+01:00'))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->pointUrl('2026-11-24T23:00:00Z'))->assertOk()->assertJsonPath('data.0.id', $worker->id);

        $next = $this->availableUrl('00:00', null, date: '2026-11-25');
        $this->getJson($next)->assertOk()->assertJsonPath('data.0.id', $worker->id);
        $this->getJson('/api/v1/workers/available?'.http_build_query([
            'started_at' => '2026-11-24T23:00:00Z', 'role' => 'worker',
        ]))->assertOk()->assertJsonPath('data.0.id', $worker->id);
        $log = DailyLog::factory()->for($manager->company)->withManager($manager)->create(['date' => '2026-11-25']);
        $this->postJson($this->attendanceUrl($log), [
            'worker_id' => $worker->id, 'started_at' => '2026-11-24T23:00:00Z', 'finished_at' => '2026-11-25T02:00:00+01:00',
        ])->assertOk();
        $this->assertDatabaseHas('worker_attendances', ['daily_log_id' => $log->id, 'started_at' => '2026-11-25 00:00:00']);
    }

    public function test_adjacent_rows_on_one_log_and_reopening_an_uncontested_period(): void
    {
        [$a, , , $worker] = $this->fixture();
        $url = $this->attendanceUrl($a);
        $this->postJson($url, $this->period($worker, '08:00', '12:00'))->assertOk();
        $id = $this->postJson($url, $this->period($worker, '12:00', '16:00'))->assertOk()->json('data.id');

        $this->patchJson("$url/$id", ['finished_at' => null])->assertOk()->assertJsonPath('data.finished_at', null);
        $this->getJson($this->availableUrl('16:30', '18:00'))->assertOk()->assertJsonCount(0, 'data');
    }

    public static function salaryPeriods(): array
    {
        return [
            'four hours' => [[['08:00', '12:00']], 4],
            'eight split hours' => [[['08:00', '12:00'], ['12:00', '16:00']], 8],
            'nine split hours' => [[['08:00', '12:00'], ['12:00', '17:00']], 9],
            'open at eight' => [[['08:00', null]], 8],
            'open at fifteen' => [[['15:00', null]], 8],
            'closed and open' => [[['08:00', '12:00'], ['12:00', null]], 12],
        ];
    }

    #[DataProvider('salaryPeriods')]
    public function test_salary_uses_actual_duration_or_eight_hours_and_reports_split_attribution(array $periods, int $hours): void
    {
        [$a, $b, $manager, $worker] = $this->fixture();
        foreach ($periods as $i => [$start, $finish]) {
            $log = [$a, $b][$i];
            Sanctum::actingAs($log->siteManagers()->sole());
            $this->postJson($this->attendanceUrl($log), $this->period($worker, $start, $finish) + ['advance_payment' => 5])->assertOk();
        }

        $worker->update(['hourly_rate' => 99]);
        $this->getJson("/api/v1/workers/{$worker->id}/salary")->assertOk()
            ->assertJsonPath('data.earned', number_format($hours * 25, 2, '.', ''))
            ->assertJsonPath('data.advances', number_format(count($periods) * 5, 2, '.', ''));

        if ($hours === 9) {
            $range = '?date_from=2026-11-23&date_to=2026-11-25';
            $data = $this->getJson('/api/v1/admin/construction-financial-summary'.$range)->assertOk()
                ->assertJsonPath('data.summary.worker_salaries.worked_hours', 9)->json('data');
            $sites = collect($data['by_construction_site'])->keyBy('construction_site_id');
            $managers = collect($data['by_site_manager'])->keyBy('site_manager_id');
            $this->assertEquals(4, $sites[$a->construction_site_id]['worker_salaries']['worked_hours']);
            $this->assertEquals(5, $sites[$b->construction_site_id]['worker_salaries']['worked_hours']);
            $this->assertEquals(4, $managers[$manager->id]['worker_salaries']['worked_hours']);
            $this->assertEquals(5, $managers[$b->siteManagers()->sole()->id]['worker_salaries']['worked_hours']);
        }
    }

    public static function machineRoles(): array
    {
        return [['excavator', WorkerRole::OPERATOR], ['truck', WorkerRole::DRIVER]];
    }

    #[DataProvider('machineRoles')]
    public function test_machine_roles_can_move_sites_and_conflict_with_manual_periods(string $type, WorkerRole $role): void
    {
        [$a, $b, $manager, $worker] = $this->fixture($role);
        $this->travelTo(Carbon::parse(self::DAY.' 18:00', 'Europe/Belgrade'));
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
        [$first, $second] = Machine::factory()->for($manager->company)->count(2)->create([
            'type' => $type, 'owner_type' => 'company', 'owner_id' => $manager->company_id,
        ])->all();
        $prefix = "/api/v1/{$type}-logs/daily-logs/";
        $period = fn (Machine $machine, string $start, string $finish) => [
            'worker_id' => $worker->id, 'machine_id' => $machine->id,
            'site_manager_started_at' => self::DAY." $start", 'site_manager_finished_at' => self::DAY." $finish",
        ];
        $this->postJson($prefix.$a->id, $period($first, '08:00', '12:00'))->assertOk();
        Sanctum::actingAs($b->siteManagers()->sole());
        $this->postJson($this->attendanceUrl($b), $this->period($worker, '11:59', '16:00'))->assertConflict();
        $this->postJson($prefix.$b->id, $period($second, '11:59', '16:00'))->assertConflict()->assertJsonPath('error_code', 'MACHINE_WORKER_TIME_CONFLICT');
        $this->getJson($this->availableUrl('12:00', '16:00', $role->value))->assertOk()->assertJsonPath('data.0.id', $worker->id);
        $this->getJson($this->pointUrl(self::DAY.'T11:59:59+01:00', $role->value))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($this->pointUrl(self::DAY.'T12:00:00+01:00', $role->value))->assertOk()->assertJsonPath('data.0.id', $worker->id);

        $this->postJson($prefix.$b->id, $period($second, '12:00', '16:00'))->assertOk();
        $this->assertDatabaseCount('worker_attendances', 2);

        $this->postJson($this->attendanceUrl($b), $this->period($worker, '16:00', '17:00'))->assertOk();
        $this->postJson($prefix.$b->id, $period($second, '16:30', '18:00'))->assertConflict()->assertJsonPath('error_code', 'MACHINE_WORKER_TIME_CONFLICT');
        $this->getJson($this->availableUrl('16:30', '18:00', $role->value))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_existing_excavator_period_conflicts_after_worker_becomes_a_driver(): void
    {
        [$a, $b, $manager, $worker] = $this->fixture(WorkerRole::OPERATOR);
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();
        $machine = fn (string $type) => Machine::factory()->for($manager->company)->create([
            'type' => $type, 'owner_type' => 'company', 'owner_id' => $manager->company_id,
        ]);
        $period = fn (Machine $machine, string $start) => [
            'worker_id' => $worker->id, 'machine_id' => $machine->id,
            'site_manager_started_at' => self::DAY." $start", 'site_manager_finished_at' => self::DAY.' 16:00',
        ];

        $this->postJson("/api/v1/excavator-logs/daily-logs/{$a->id}", $period($machine('excavator'), '08:00'))
            ->assertOk();
        $worker->update(['role' => WorkerRole::DRIVER]);
        Sanctum::actingAs($b->siteManagers()->sole());
        $this->postJson("/api/v1/truck-logs/daily-logs/{$b->id}", $period($machine('truck'), '12:00'))
            ->assertConflict()->assertJsonPath('error_code', 'MACHINE_WORKER_TIME_CONFLICT');
        $this->assertDatabaseCount('worker_attendances', 1);
    }

    private function fixture(WorkerRole $role = WorkerRole::WORKER): array
    {
        $this->travelTo(Carbon::parse(self::DAY.' 10:00', 'Europe/Belgrade'));
        $a = DailyLog::factory()->create(['date' => self::DAY]);
        $manager = $a->siteManagers()->sole();
        $b = DailyLog::factory()->for($manager->company)->create(['date' => self::DAY]);
        $worker = Worker::factory()->for($manager->company)->create(['role' => $role, 'hourly_rate' => 25]);
        Sanctum::actingAs($manager);

        return [$a, $b, $manager, $worker];
    }

    private function attendanceUrl(DailyLog $log): string
    {
        return "/api/v1/daily-logs/{$log->id}/worker-attendances";
    }

    private function period(Worker $worker, string $start, ?string $finish): array
    {
        return ['worker_id' => $worker->id, 'started_at' => self::DAY."T$start:00+01:00",
            'finished_at' => $finish === null ? null : self::DAY."T$finish:00+01:00"];
    }

    private function pointUrl(string $at, string $role = 'worker'): string
    {
        return '/api/v1/workers/available?'.http_build_query(['role' => $role, 'at' => $at]);
    }

    private function availableUrl(string $start, ?string $finish, string $role = 'worker', string $date = self::DAY): string
    {
        return '/api/v1/workers/available?'.http_build_query(array_filter([
            'role' => $role, 'started_at' => "{$date}T$start:00+01:00",
            'finished_at' => $finish === null ? null : "{$date}T$finish:00+01:00",
        ]));
    }
}
