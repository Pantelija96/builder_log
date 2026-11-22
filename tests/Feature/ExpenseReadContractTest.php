<?php

namespace Tests\Feature;

use App\Models\CashAdvance;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseReadContractTest extends TestCase
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

    public function test_daily_list_and_history_keep_their_distinct_shapes_and_pagination(): void
    {
        $log = DailyLog::factory()->create(['date' => '2026-02-02']);
        $earlierLog = DailyLog::factory()->withManager($log->siteManagers()->firstOrFail())->create([
            'company_id' => $log->company_id,
            'construction_site_id' => $log->construction_site_id,
            'date' => '2026-02-01',
        ]);
        $anotherSite = ConstructionSite::factory()->for($log->company)->create();
        $anotherManager = Worker::factory()->siteManager()->for($log->company)->create();
        $admin = Worker::factory()->admin()->for($log->company)->create();
        $recent = $this->expense($log, ['amount' => 25.5]);
        $earlier = $this->expense($earlierLog, ['amount' => 10]);
        $this->expense($log, ['construction_site_id' => $anotherSite->id, 'daily_log_id' => null, 'amount' => 5]);
        $shared = $this->expense($log, ['site_manager_id' => $anotherManager->id, 'amount' => 30]);
        $adminExpense = $this->expense($log, [
            'daily_log_id' => null, 'site_manager_id' => null, 'created_by' => $admin->id, 'amount' => 300,
        ]);
        $advance = CashAdvance::create([
            'company_id' => $log->company_id,
            'site_manager_id' => $log->siteManagers()->firstOrFail()->id,
            'created_by' => $admin->id,
            'amount' => 100,
            'date' => '2026-02-01',
        ]);
        Sanctum::actingAs($log->siteManagers()->firstOrFail());

        $url = "/api/v1/daily-logs/{$log->id}/expenses";
        $list = $this->getJson("$url?sort=id&direction=asc&limit=1")->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.expenses.0.amount', '25.50')
            ->assertJsonPath('data.expenses.1.site_manager_id', $anotherManager->id)
            ->assertJsonPath('data.expenses.0.site_manager_id', $log->siteManagers()->firstOrFail()->id)
            ->assertJsonMissingPath('data.expenses.0.site_manager')
            ->assertJsonMissingPath('data.cash_advances');
        $this->assertSame(['expenses', 'summary'], array_keys($list->json('data')));
        $this->assertSame([$recent->id, $shared->id], array_column($list->json('data.expenses'), 'id'));
        $this->assertSame([
            'id', 'title', 'description', 'amount', 'date', 'company_id', 'daily_log_id',
            'construction_site_id', 'site_manager_id', 'created_at', 'updated_at',
        ], array_keys($list->json('data.expenses.0')));
        $list->assertJsonPath('data.summary.site_manager.id', $log->siteManagers()->firstOrFail()->id)
            ->assertJsonPath('data.summary.advanced', 100)
            ->assertJsonPath('data.summary.spent', 40.5)
            ->assertJsonPath('data.summary.remaining', 59.5)
            ->assertJsonPath('data.summary.utilization', 40.5);

        $history = $this->getJson("$url/history?sort=date&direction=desc&limit=1&offset=1")->assertOk()
            ->assertJsonCount(1, 'data.expenses')
            ->assertJsonPath('data.expenses.0.id', $earlier->id)
            ->assertJsonPath('data.expenses.0.site_manager.id', $log->siteManagers()->firstOrFail()->id)
            ->assertJsonPath('data.expenses.0.daily_log.id', $earlierLog->id)
            ->assertJsonPath('data.expenses.0.construction_site.id', $log->construction_site_id)
            ->assertJsonPath('data.expenses.0.creator.id', $log->siteManagers()->firstOrFail()->id)
            ->assertJsonPath('data.expenses.0.attachments', [])
            ->assertJsonPath('data.cash_advances.0.id', $advance->id)
            ->assertJsonPath('data.cash_advances.0.amount', '100.00')
            ->assertJsonPath('data.cash_advances.0.creator.id', $admin->id);
        $this->assertSame(['expenses', 'summary', 'cash_advances'], array_keys($history->json('data')));
        $this->assertSame($list->json('data.summary'), $history->json('data.summary'));

        foreach ([$url, "$url/history"] as $endpoint) {
            $this->getJson("$endpoint?date_from=2026-03-01")->assertOk()
                ->assertJsonPath('data.expenses', [])
                ->assertJsonPath('data.summary.spent', 40.5);
        }

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/expenses')->assertOk()
            ->assertJsonPath('data.total', '300.00')
            ->assertJsonPath('data.expenses.0.id', $adminExpense->id)
            ->assertJsonPath('data.expenses.0.site_manager_id', null)
            ->assertJsonPath('data.expenses.0.daily_log_id', null)
            ->assertJsonPath('data.expenses.0.site_manager', null)
            ->assertJsonPath('data.expenses.0.daily_log', null);
    }

    private function expense(DailyLog $log, array $overrides): Expense
    {
        return Expense::create($overrides + [
            'company_id' => $log->company_id,
            'construction_site_id' => $log->construction_site_id,
            'daily_log_id' => $log->id,
            'site_manager_id' => $log->siteManagers()->firstOrFail()->id,
            'created_by' => $log->siteManagers()->firstOrFail()->id,
            'date' => $log->date,
            'title' => 'Materials',
        ]);
    }
}
