<?php

namespace Tests\Feature;

use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Excavator;
use App\Models\ExcavatorLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClosePreviousDayContractTest extends TestCase
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

    public function test_one_previous_date_is_locked_while_today_is_left_open(): void
    {
        $this->travelTo(today()->setDate(2026, 2, 3)->setTime(0, 15));
        $previous = DailyLog::factory()->create(['date' => '2026-02-02']);
        $today = DailyLog::factory()->create(['date' => '2026-02-03']);

        $this->artisan('daily:close-previous-day')->assertSuccessful();
        self::assertTrue($previous->fresh()->is_locked);
        self::assertNull($previous->fresh()->locked_by);
        self::assertFalse($today->fresh()->is_locked);
        $this->assertSame([$previous->id], $this->lockOrder());
    }

    public function test_missed_dates_run_in_order_and_repeated_machine_closing_keeps_current_totals(): void
    {
        $this->travelTo(today()->setDate(2026, 2, 4)->setTime(0, 15));
        $newer = DailyLog::factory()->create(['date' => '2026-02-03']);
        $older = DailyLog::factory()->create(['date' => '2026-02-01']);
        $sameDate = DailyLog::factory()->create(['date' => '2026-02-01']);
        $locked = DailyLog::factory()->locked()->create(['date' => '2026-02-02', 'locked_at' => now()->subDays(2)]);
        $today = DailyLog::factory()->create(['date' => '2026-02-04']);
        $released = Worker::factory()->for($older->company)->create(['is_available' => false]);
        $working = Worker::factory()->for($today->company)->create(['is_available' => false]);
        $this->attendance($older, $released);
        $this->attendance($newer, $working);
        $this->attendance($today, $working);

        $secondManager = Worker::factory()->for($older->company)->siteManager()->create();
        $older->siteManagers()->attach($secondManager);
        $excavatorAssignment = $this->assignment($older, 'excavator');
        $excavatorAssignment->update(['site_manager_id' => $secondManager->id]);
        $excavator = Excavator::create(['machine_id' => $excavatorAssignment->machine_id, 'total_work_hours' => 100]);
        $excavatorLog = ExcavatorLog::create([
            'machine_assignment_id' => $excavatorAssignment->id,
            'worker_id' => $excavatorAssignment->worker_id,
            'created_by' => $excavatorAssignment->created_by,
        ]);
        $truckAssignment = $this->assignment($older, 'truck');
        $truckLog = TruckLog::create([
            'machine_assignment_id' => $truckAssignment->id,
            'worker_id' => $truckAssignment->worker_id,
            'created_by' => $truckAssignment->created_by,
        ]);

        $defaultHours = (float) Env::get('DEFAULT_MACHINE_WORK_HOURS', 9);
        $this->artisan('daily:close-previous-day')->assertSuccessful();
        self::assertSame([$older->id, $sameDate->id, $newer->id], $this->lockOrder());
        self::assertTrue($sameDate->fresh()->is_locked);
        self::assertEquals($locked->locked_at, $locked->fresh()->locked_at);
        self::assertFalse($today->fresh()->is_locked);
        self::assertTrue($released->fresh()->is_available);
        self::assertFalse($working->fresh()->is_available);
        $finished = $excavatorLog->fresh();
        self::assertSame('2026-02-01 23:59:59', $finished->site_manager_finished_at->toDateTimeString());
        self::assertEquals($finished->site_manager_finished_at, $finished->operator_finished_at);
        self::assertSame($defaultHours, (float) $finished->work_hours);
        self::assertSame(100 + $defaultHours, (float) $excavator->fresh()->total_work_hours);
        self::assertNull($truckLog->fresh()->site_manager_finished_at);
        self::assertNull($truckLog->fresh()->operator_finished_at);
        $this->assertDatabaseCount('worker_attendances', 3);

        $this->travel(1)->hours();
        $this->artisan('daily:close-previous-day')->assertSuccessful();
        self::assertSame([$older->id, $sameDate->id, $newer->id], $this->lockOrder());
        self::assertSame(100 + 2 * $defaultHours, (float) $excavator->fresh()->total_work_hours);
        self::assertEquals($finished->site_manager_finished_at, $excavatorLog->fresh()->site_manager_finished_at);
        self::assertNull($truckLog->fresh()->site_manager_finished_at);
        $this->assertDatabaseCount('worker_attendances', 3);
    }

    private function lockOrder(): array
    {
        return DB::table('activity_logs')
            ->where('event', LogEvent::DAILY_LOG_LOCKED->value)
            ->orderBy('id')->pluck('daily_log_id')->all();
    }

    private function attendance(DailyLog $log, Worker $worker): void
    {
        WorkerAttendance::create([
            'company_id' => $log->company_id,
            'construction_site_id' => $log->construction_site_id,
            'daily_log_id' => $log->id,
            'site_manager_id' => $log->siteManagers()->firstOrFail()->id,
            'worker_id' => $worker->id,
            'created_by' => $log->siteManagers()->firstOrFail()->id,
            'date' => $log->date,
            'started_at' => $log->date->copy()->addHours(8),
        ]);
    }

    private function assignment(DailyLog $log, string $type): MachineAssignment
    {
        $machine = Machine::factory()->for($log->company)->create([
            'type' => $type,
            'owner_type' => 'company',
            'owner_id' => $log->company_id,
        ]);

        return MachineAssignment::create([
            'company_id' => $log->company_id,
            'construction_site_id' => $log->construction_site_id,
            'daily_log_id' => $log->id,
            'site_manager_id' => $log->siteManagers()->firstOrFail()->id,
            'worker_id' => $log->siteManagers()->firstOrFail()->id,
            'created_by' => $log->siteManagers()->firstOrFail()->id,
            'date' => $log->date,
            'machine_id' => $machine->id,
        ]);
    }
}
