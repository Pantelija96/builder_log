<?php

namespace Tests\Feature;

use App\Enums\WorkerRole;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Subcontractor;
use App\Models\SubcontractorLog;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiManagerReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_records_count_once_and_salary_uses_confirmed_periods(): void
    {
        $this->travelTo(now()->setDate(2026, 2, 10)->setTime(9, 0));
        $this->mock(WorkerPushNotificationService::class)->shouldReceive('send')->zeroOrMoreTimes();

        $log = DailyLog::factory()->create(['date' => today()]);
        $manager = $log->siteManagers()->sole();
        $otherManager = Worker::factory()->for($log->company)->siteManager()->create();
        $log->siteManagers()->attach($otherManager);
        $admin = Worker::factory()->for($log->company)->admin()->create();
        $ordinary = Worker::factory()->for($log->company)->create(['hourly_rate' => 25]);
        $children = [
            'company_id' => $log->company_id,
            'daily_log_id' => $log->id,
            'construction_site_id' => $log->construction_site_id,
            'date' => '2026-02-10',
            'site_manager_id' => $manager->id,
            'created_by' => $manager->id,
        ];

        Expense::create($children + ['title' => 'Fuel', 'amount' => 125]);
        $subcontractor = Subcontractor::create(['company_id' => $log->company_id, 'name' => 'Concrete subcontractor']);
        SubcontractorLog::create($children + [
            'subcontractor_id' => $subcontractor->id,
            'worker_count' => 1,
            'started_at' => '2026-02-10 08:00:00',
            'finished_at' => '2026-02-10 10:00:00',
        ]);
        $workerPayment = fn (Worker $worker) => WorkerPayment::create([
            'company_id' => $log->company_id,
            'worker_id' => $worker->id,
            'created_by' => $admin->id,
            'amount' => 10,
            'date' => '2026-02-10',
        ]);

        Sanctum::actingAs($manager);
        $attendanceUrl = "/api/v1/daily-logs/{$log->id}/worker-attendances";
        $this->postJson($attendanceUrl, [
            'worker_id' => $ordinary->id,
            'started_at' => '2026-02-10 08:00:00',
            'finished_at' => '2026-02-10 12:00:00',
            'advance_payment' => 5,
        ])->assertOk();
        $this->postJson($attendanceUrl, [
            'worker_id' => $ordinary->id,
            'started_at' => '2026-02-10 12:00:00',
        ])->assertInternalServerError();
        $ordinary->update(['hourly_rate' => 200]);
        $workerPayment($ordinary);

        $machineWorkers = [];
        foreach (['excavator' => WorkerRole::OPERATOR, 'truck' => WorkerRole::DRIVER] as $type => $role) {
            $worker = Worker::factory()->for($log->company)->create(['role' => $role, 'hourly_rate' => 30]);
            $machineWorkers[] = $worker;
            $machine = Machine::factory()->for($log->company)->create([
                'type' => $type, 'owner_type' => 'company', 'owner_id' => $log->company_id,
            ]);
            Sanctum::actingAs($worker);
            $prefix = "/api/v1/{$type}-logs";
            $selfId = $this->postJson("$prefix/{$role->value}", [
                'construction_site_id' => $log->construction_site_id,
                'machine_id' => $machine->id,
                'operator_started_at' => '2026-02-10 08:00:00',
            ])->assertOk()->json('data.id');
            Sanctum::actingAs($admin);
            $this->getJson("/api/v1/workers/{$worker->id}/salary")->assertOk()
                ->assertJsonPath('data.earned', '0.00')->assertJsonCount(0, 'data.attendances');

            Sanctum::actingAs($otherManager);
            $url = "$prefix/daily-logs/{$log->id}";
            $payload = ['machine_id' => $machine->id, 'worker_id' => $worker->id];
            $this->postJson($url, $payload + [
                'site_manager_started_at' => '2026-02-10 08:00:00',
                'site_manager_finished_at' => '2026-02-10 10:00:00',
                'advance_payment' => 5,
            ])->assertOk()->assertJsonPath('data.id', $selfId);
            $this->postJson($url, $payload + [
                'site_manager_started_at' => '2026-02-10 10:00:00',
                'site_manager_finished_at' => '2026-02-10 11:00:00',
            ])->assertOk();
            $this->postJson($url, $payload + [
                'site_manager_started_at' => '2026-02-10 09:30:00',
                'site_manager_finished_at' => '2026-02-10 10:30:00',
            ])->assertInternalServerError();
            $workerPayment($worker);

            if ($type === 'excavator') {
                ExcavatorLog::whereIn('machine_assignment_id', MachineAssignment::where('machine_id', $machine->id)->select('id'))
                    ->update(['work_hours' => 1.5]);
            }
        }

        $foreign = DailyLog::factory()->create(['date' => today()]);
        Expense::create(array_replace($children, [
            'company_id' => $foreign->company_id, 'daily_log_id' => $foreign->id,
            'construction_site_id' => $foreign->construction_site_id,
            'site_manager_id' => $foreign->siteManagers()->sole()->id,
            'created_by' => $foreign->siteManagers()->sole()->id,
            'title' => 'Foreign expense', 'amount' => 900,
        ]));

        Sanctum::actingAs($admin);
        $range = '?date_from=2026-02-09&date_to=2026-02-11';
        $site = "/api/v1/construction-sites/{$log->construction_site_id}";
        $this->getJson("$site/financial-summary$range")->assertOk()
            ->assertJsonCount(1, 'data.expenses')->assertJsonCount(3, 'data.cash_advances');
        $this->getJson("$site/statistics$range")->assertOk()
            ->assertJsonPath('data.total_expenses', 140)
            ->assertJsonPath('data.total_worker_hours', 10)
            ->assertJsonPath('data.total_subcontractor_hours', 2)
            ->assertJsonPath('data.total_machine_hours', 3);
        $this->getJson("/api/v1/site-managers/{$manager->id}/overall$range")->assertOk()
            ->assertJsonCount(1, 'data.0.expenses')->assertJsonCount(1, 'data.0.worker_attendance');
        $this->getJson("/api/v1/site-managers/{$otherManager->id}/overall$range")->assertOk()
            ->assertJsonCount(0, 'data.0.expenses')->assertJsonCount(4, 'data.0.worker_attendance');
        $this->getJson('/api/v1/financial-summary')->assertOk()->assertJsonCount(2, 'data');
        $global = $this->getJson("/api/v1/admin/construction-financial-summary$range")->assertOk()
            ->assertJsonPath('data.summary.expenses.total', 125)
            ->assertJsonPath('data.summary.expenses.count', 1)
            ->assertJsonPath('data.summary.worker_salaries.earned', 280)
            ->assertJsonPath('data.summary.worker_salaries.worked_hours', 10)
            ->assertJsonCount(1, 'data.by_construction_site');
        $managers = collect($global->json('data.by_site_manager'))->keyBy('site_manager_id');
        $this->assertEquals(125, $managers[$manager->id]['expenses']['site_manager']);
        $this->assertEquals(0, $managers[$otherManager->id]['expenses']['site_manager']);
        $this->assertEquals(180, $managers[$otherManager->id]['worker_salaries']['earned']);

        $this->getJson("/api/v1/workers/{$ordinary->id}/salary$range")->assertOk()
            ->assertJsonPath('data.earned', '100.00')->assertJsonPath('data.advances', '5.00')
            ->assertJsonPath('data.payments', '10.00')->assertJsonPath('data.outstanding', '85.00');
        foreach ($machineWorkers as $worker) {
            $this->getJson("/api/v1/workers/{$worker->id}/salary$range")->assertOk()
                ->assertJsonPath('data.earned', '90.00')->assertJsonPath('data.advances', '5.00')
                ->assertJsonPath('data.payments', '10.00')->assertJsonPath('data.outstanding', '75.00')
                ->assertJsonCount(2, 'data.attendances');
            $this->getJson("/api/v1/workers/{$worker->id}/salary?date_to=2026-02-09")->assertOk()
                ->assertJsonPath('data.earned', '0.00')->assertJsonCount(0, 'data.attendances');
        }
        $this->getJson("/api/v1/admin/worker-salaries$range")->assertOk()->assertJsonCount(3, 'data.workers');
    }
}
