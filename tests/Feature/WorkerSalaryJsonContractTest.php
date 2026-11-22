<?php

namespace Tests\Feature;

use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerSalaryJsonContractTest extends TestCase
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

    public function test_list_and_detail_keep_decimal_strings_and_distinct_envelopes(): void
    {
        $log = DailyLog::factory()->create(['date' => '2026-02-01']);
        $admin = Worker::factory()->admin()->for($log->company)->create();
        $worker = Worker::factory()->for($log->company)->create(['hourly_rate' => 900]);
        WorkerAttendance::create([
            'company_id' => $log->company_id,
            'daily_log_id' => $log->id,
            'construction_site_id' => $log->construction_site_id,
            'site_manager_id' => $log->siteManagers()->firstOrFail()->id,
            'worker_id' => $worker->id,
            'created_by' => $admin->id,
            'date' => '2026-02-01',
            'started_at' => '2026-02-01 08:00:00',
            'finished_at' => '2026-02-01 10:00:00',
            'hourly_rate' => 125.25,
            'advance_payment' => 20.25,
        ]);
        WorkerPayment::create([
            'company_id' => $log->company_id,
            'worker_id' => $worker->id,
            'created_by' => $admin->id,
            'date' => '2026-02-01',
            'amount' => 50,
        ]);
        Sanctum::actingAs($admin);

        $filter = '?date_from=2026-02-01&date_to=2026-02-01';
        $list = $this->getJson('/api/v1/admin/worker-salaries'.$filter)->assertOk()
            ->assertJsonCount(1, 'data.workers')
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', null);
        self::assertSame(['success', 'message', 'data'], array_keys($list->json()));
        self::assertSame(['workers', 'summary'], array_keys($list->json('data')));
        self::assertSame([
            'period' => ['earned' => '250.50', 'advances' => '20.25', 'worked_hours' => '2.00'],
            'total' => ['earned' => '250.50', 'advances' => '20.25', 'payments' => '50.00', 'outstanding' => '180.25'],
        ], $list->json('data.summary'));

        $detail = $this->getJson("/api/v1/workers/{$worker->id}/salary".$filter)->assertOk()
            ->assertJsonPath('data.worker_id', $worker->id)
            ->assertJsonPath('data.earned', '250.50')
            ->assertJsonPath('data.advances', '20.25')
            ->assertJsonPath('data.payments', '50.00')
            ->assertJsonPath('data.outstanding', '180.25')
            ->assertJsonPath('data.attendances.0.hourly_rate', '125.25')
            ->assertJsonMissingPath('data.summary');
        self::assertSame(['data'], array_keys($detail->json()));

        $this->getJson('/api/v1/admin/worker-salaries?date_from=2026-02-02')->assertOk()->assertExactJson([
            'success' => true,
            'message' => null,
            'data' => [
                'workers' => [],
                'summary' => [
                    'period' => ['earned' => '0.00', 'advances' => '0.00', 'worked_hours' => '0.00'],
                    'total' => ['earned' => '0.00', 'advances' => '0.00', 'payments' => '0.00', 'outstanding' => '0.00'],
                ],
            ],
        ]);
    }
}
