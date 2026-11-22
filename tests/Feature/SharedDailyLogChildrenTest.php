<?php

namespace Tests\Feature;

use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\Enums\WorkerRole;
use App\Models\Attachment;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Expense;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Note;
use App\Models\Subcontractor;
use App\Models\SubcontractorLog;
use App\Models\Supplier;
use App\Models\Worker;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedDailyLogChildrenTest extends TestCase
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

    #[DataProvider('modules')]
    public function test_shared_records_preserve_attribution_and_require_parent_membership(string $path, string $model, array $payload): void
    {
        $this->mock(WorkerPushNotificationService::class);
        config(['app.debug' => false]);
        [$log, $a, $b, $nonmember, $admin] = $this->sharedLog();
        $foreign = Worker::factory()->siteManager()->create();

        if ($model === DeliveryNote::class) {
            $payload['supplier_id'] = Supplier::create([
                'company_id' => $log->company_id, 'name' => 'Supplier',
            ])->id;
        }
        if ($model === SubcontractorLog::class) {
            $payload['subcontractor_id'] = Subcontractor::create([
                'company_id' => $log->company_id, 'name' => 'Subcontractor',
            ])->id;
        }

        $url = "/api/v1/daily-logs/{$log->id}/$path";
        $readUrl = $model === SubcontractorLog::class ? '/api/v1/subcontractor-logs' : $url;
        $recordPath = $model === Expense::class ? 'data.expenses' : 'data';
        $changes = match ($model) {
            Expense::class => ['amount' => 40],
            Note::class => ['note' => 'Updated crew'],
            DeliveryNote::class => ['name' => 'Updated delivery'],
            SubcontractorLog::class => ['worker_count' => 4],
        };
        $update = array_merge($payload, $changes, ['reason' => 'Correction', 'site_manager_id' => $b->id]);

        Sanctum::actingAs($admin);
        $this->postJson($url, $payload)->assertUnprocessable();

        Sanctum::actingAs($a);
        $id = $this->postJson($url, $payload)->assertOk()->json('data.id');
        $record = $model::findOrFail($id);
        $this->assertSame($a->id, $record->created_by);
        $this->assertSame($a->id, $record->site_manager_id);

        Sanctum::actingAs($b);
        $this->getJson($readUrl)->assertOk()->assertJsonPath("$recordPath.0.id", $id);
        if ($model === SubcontractorLog::class) {
            $this->postJson($url, $payload)->assertStatus(500);
            $this->assertDatabaseCount('subcontractor_logs', 1);
        }
        $this->patchJson("$url/$id", $update)->assertOk();
        $this->assertDatabaseHas($record->getTable(), ['id' => $id] + $changes);
        $this->assertSame($a->id, $record->fresh()->site_manager_id);
        $this->assertSame($a->id, $record->fresh()->created_by);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $b->id, 'auditable_id' => $id, 'auditable_type' => $record->getMorphClass(),
        ]);

        $otherLog = DailyLog::factory()->for($log->company)->withManager($b)->create();
        $wrongParent = "/api/v1/daily-logs/{$otherLog->id}/$path/$id";
        $this->patchJson($wrongParent, $update)->assertNotFound();
        $this->deleteJson($wrongParent, ['reason' => 'Correction'])->assertNotFound();

        Sanctum::actingAs($nonmember);
        if ($model === SubcontractorLog::class) {
            $this->getJson($readUrl)->assertOk()->assertJsonCount(0, 'data');
        } else {
            $this->getJson($readUrl)->assertForbidden();
        }
        $this->postJson($url, $payload)->assertForbidden();
        $this->patchJson("$url/$id", $update)->assertForbidden();
        $this->deleteJson("$url/$id", ['reason' => 'Correction'])->assertForbidden();

        Sanctum::actingAs($foreign);
        if ($model === SubcontractorLog::class) {
            $this->getJson($readUrl)->assertOk()->assertJsonCount(0, 'data');
        } else {
            $this->getJson($readUrl)->assertNotFound();
        }
        $this->postJson($url, $payload)->assertNotFound();
        $this->patchJson("$url/$id", $update)->assertNotFound();
        $this->deleteJson("$url/$id", ['reason' => 'Correction'])->assertNotFound();

        Sanctum::actingAs($b);
        $log->update(['is_locked' => true]);
        $this->getJson($readUrl)->assertOk()->assertJsonPath("$recordPath.0.id", $id);
        $this->postJson($url, $payload)->assertStatus(500);
        $this->patchJson("$url/$id", $update)->assertStatus(500);
        $this->deleteJson("$url/$id", ['reason' => 'Correction'])->assertStatus(500);
        $log->update(['is_locked' => false]);
        $this->deleteJson("$url/$id", ['reason' => 'Correction'])->assertOk();
        $this->assertNull($model::find($id));
    }

    public static function modules(): array
    {
        return [
            'expenses' => ['expenses', Expense::class, ['title' => 'Supplies', 'amount' => 25.50]],
            'notes' => ['notes', Note::class, ['note' => 'Crew', 'worker_count' => 2, 'notify_admin' => false]],
            'delivery' => ['delivery-notes', DeliveryNote::class, ['name' => 'Delivery']],
            'subcontractors' => ['subcontractors', SubcontractorLog::class, ['worker_count' => 3, 'started_at' => '2026-02-01 08:00:00']],
        ];
    }

    public function test_expense_totals_and_history_follow_record_attribution_once(): void
    {
        [$log, $a, $b, , $admin] = $this->sharedLog();
        $url = "/api/v1/daily-logs/{$log->id}/expenses";
        Sanctum::actingAs($a);
        $id = $this->postJson($url, ['title' => 'Fuel', 'amount' => 25.50])
            ->assertOk()->json('data.id');
        $this->getJson("$url/history")->assertOk()->assertJsonPath('data.expenses.0.id', $id)
            ->assertJsonPath('data.summary.spent', 25.5);

        Sanctum::actingAs($b);
        $this->getJson($url)->assertOk()->assertJsonPath('data.expenses.0.id', $id)
            ->assertJsonPath('data.summary.spent', 0);
        $this->getJson("$url/history")->assertOk()->assertJsonCount(0, 'data.expenses');
        $this->patchJson("$url/$id", ['title' => 'Fuel corrected', 'amount' => 30.25])->assertOk();

        Sanctum::actingAs($admin);
        $site = $log->construction_site_id;
        $date = $log->date->toDateString();
        $this->getJson("/api/v1/construction-sites/$site/financial-summary?date_from=$date&date_to=$date")
            ->assertOk()->assertJsonCount(1, 'data.expenses')->assertJsonPath('data.expenses.0.amount', '30.25');
        $this->getJson("/api/v1/site-managers/{$a->id}/overall")->assertOk()
            ->assertJsonPath('data.0.expenses.0.id', $id);
        $this->getJson("/api/v1/site-managers/{$b->id}/overall")->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/expenses', [
            'construction_site_id' => $site, 'title' => 'Admin fuel', 'amount' => 15, 'date' => $date,
        ])->assertOk()->assertJsonPath('data.daily_log_id', null)->assertJsonPath('data.site_manager_id', null);
        $this->assertDatabaseHas('expenses', [
            'created_by' => $admin->id, 'site_manager_id' => null, 'daily_log_id' => null,
        ]);
    }

    public function test_ordinary_attendance_is_shared_unique_and_deletion_keeps_today_availability(): void
    {
        config(['app.debug' => false]);
        $this->travelTo(today()->setDate(2026, 2, 2)->setHour(12));
        [$log, $a, $b, $nonmember, $admin] = $this->sharedLog();
        $worker = Worker::factory()->for($log->company)->create();
        $otherSite = ConstructionSite::factory()->for($log->company)->create();
        $sameDay = DailyLog::factory()->for($log->company)->withManager($b)->create([
            'construction_site_id' => $otherSite->id, 'date' => '2026-02-01',
        ]);
        $todayLog = DailyLog::factory()->for($log->company)->withManager($b)->create([
            'date' => '2026-02-02',
        ]);
        $payload = ['worker_id' => $worker->id, 'started_at' => '2026-02-01 08:00:00'];
        $url = "/api/v1/daily-logs/{$log->id}/worker-attendances";
        Sanctum::actingAs($admin);
        $this->postJson($url, $payload)->assertUnprocessable();
        Sanctum::actingAs($a);
        $id = $this->postJson($url, $payload)->assertOk()->json('data.id');
        Sanctum::actingAs($b);
        $this->getJson($url)->assertOk()->assertJsonPath('data.0.id', $id);
        $this->patchJson("$url/$id", ['advance_payment' => 12])->assertOk()
            ->assertJsonPath('data.site_manager_id', $a->id)->assertJsonPath('data.created_by', $a->id);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $b->id, 'auditable_id' => $id, 'auditable_type' => 'worker_attendance',
        ]);
        $this->postJson($url, $payload)->assertStatus(500);
        $this->postJson("/api/v1/daily-logs/{$sameDay->id}/worker-attendances", $payload)->assertConflict();
        $next = $this->postJson("/api/v1/daily-logs/{$todayLog->id}/worker-attendances", [
            'worker_id' => $worker->id, 'started_at' => '2026-02-02 08:00:00',
        ])->assertOk()->json('data.id');

        Sanctum::actingAs($nonmember);
        $this->getJson($url)->assertForbidden();
        $this->patchJson("$url/$id", ['advance_payment' => 12])->assertForbidden();
        Sanctum::actingAs(Worker::factory()->siteManager()->create());
        $this->getJson($url)->assertNotFound();
        $this->deleteJson("$url/$id", ['reason' => 'Correction'])->assertNotFound();

        Sanctum::actingAs($b);
        $log->update(['is_locked' => true]);
        $this->patchJson("$url/$id", ['advance_payment' => 13])->assertStatus(500);
        $log->update(['is_locked' => false]);
        $this->deleteJson("$url/$id", ['reason' => 'Correction'])->assertOk();
        $this->assertDatabaseMissing('worker_attendances', ['id' => $id]);
        $this->assertFalse($worker->fresh()->is_available);
        $this->deleteJson("/api/v1/daily-logs/{$todayLog->id}/worker-attendances/$next", ['reason' => 'Correction'])->assertOk();
        $this->assertTrue($worker->fresh()->is_available);
    }

    #[DataProvider('machineRoles')]
    public function test_machine_sync_can_keep_multiple_periods(WorkerRole $role, string $type): void
    {
        [$log, $a] = $this->sharedLog();
        $worker = Worker::factory()->for($log->company)->create(['role' => $role]);
        $machine = Machine::factory()->for($log->company)->create([
            'type' => $type, 'owner_type' => 'company', 'owner_id' => $log->company_id,
        ]);
        foreach ([['08', '10'], ['11', '13']] as [$start, $end]) {
            $assignment = MachineAssignment::create([
                'company_id' => $log->company_id, 'construction_site_id' => $log->construction_site_id,
                'daily_log_id' => $log->id, 'machine_id' => $machine->id, 'worker_id' => $worker->id,
                'site_manager_id' => $a->id, 'created_by' => $a->id, 'date' => $log->date,
            ]);
            $attendance = app(SyncMachineWorkerAttendanceAction::class)->execute(
                $assignment, "2026-02-01 $start:00:00", "2026-02-01 $end:00:00"
            );
            $this->assertNull($attendance->ordinary_worker_day);
        }
        $this->assertSame(2, $worker->attendances()->count());
    }

    public static function machineRoles(): array
    {
        return [
            'operator' => [WorkerRole::OPERATOR, 'excavator'],
            'driver' => [WorkerRole::DRIVER, 'truck'],
        ];
    }

    public function test_migration_reports_conflicting_row_ids_without_altering_them(): void
    {
        [$log, $a] = $this->sharedLog();
        $worker = Worker::factory()->for($log->company)->create();
        $attributes = [
            'company_id' => $log->company_id, 'daily_log_id' => $log->id,
            'construction_site_id' => $log->construction_site_id, 'site_manager_id' => $a->id,
            'worker_id' => $worker->id, 'date' => $log->date->toDateString(),
            'created_by' => $a->id, 'started_at' => '2026-02-01 08:00:00',
        ];
        $first = DB::table('worker_attendances')->insertGetId($attributes);
        $second = DB::table('worker_attendances')->insertGetId($attributes);
        $migration = require database_path('migrations/2026_11_22_130000_enforce_ordinary_worker_daily_attendance.php');

        try {
            $migration->up();
            self::fail('Expected historical conflicts to stop the migration.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('"attendance_ids":['.$first.','.$second.']', $exception->getMessage());
        }

        $this->assertDatabaseCount('worker_attendances', 2);
        $this->assertDatabaseHas('worker_attendances', ['id' => $first, 'ordinary_worker_day' => null]);
        $this->assertDatabaseHas('worker_attendances', ['id' => $second, 'ordinary_worker_day' => null]);
    }

    public function test_child_references_and_indirect_expense_reads_respect_company_and_membership(): void
    {
        [$log, $a, , $nonmember] = $this->sharedLog();
        $foreign = Worker::factory()->siteManager()->create();
        $supplier = Supplier::create(['company_id' => $foreign->company_id, 'name' => 'Foreign supplier']);
        $subcontractor = Subcontractor::create(['company_id' => $foreign->company_id, 'name' => 'Foreign subcontractor']);

        Sanctum::actingAs($a);
        $base = "/api/v1/daily-logs/{$log->id}";
        $this->postJson("$base/worker-attendances", [
            'worker_id' => $foreign->id, 'started_at' => '2026-02-01 08:00:00',
        ])->assertNotFound();
        $this->postJson("$base/delivery-notes", ['supplier_id' => $supplier->id, 'name' => 'Delivery'])->assertNotFound();
        $this->postJson("$base/subcontractors", ['subcontractor_id' => $subcontractor->id, 'worker_count' => 3])->assertNotFound();
        $this->postJson("$base/expenses", ['title' => 'Fuel', 'amount' => 20])->assertOk();

        $site = $log->construction_site_id;
        $summary = "/api/v1/construction-sites/$site/financial-summary?date_from=2026-02-01&date_to=2026-02-01";
        Sanctum::actingAs($nonmember);
        $this->getJson($summary)->assertOk()->assertJsonCount(0, 'data.expenses');
        $this->getJson("/api/v1/site-managers/{$a->id}/overall")->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($foreign);
        $this->getJson($summary)->assertNotFound();
        $this->getJson("/api/v1/site-managers/{$a->id}/overall")->assertNotFound();
    }

    public function test_daily_and_expense_attachments_require_the_actual_owner_context(): void
    {
        config(['app.debug' => false, 'filesystems.attachments.disk' => 'm2-shared-attachments']);
        Storage::fake('m2-shared-attachments');
        [$log, $a, $b, $nonmember, $admin] = $this->sharedLog();
        $foreign = Worker::factory()->siteManager()->create();
        $url = "/api/v1/daily-logs/{$log->id}/attachments";
        Sanctum::actingAs($a);
        $attachment = $this->upload($url, 'daily.pdf')->assertOk()->json('data.0.id');
        $expense = $this->postJson("/api/v1/daily-logs/{$log->id}/expenses", [
            'title' => 'Fuel', 'amount' => 25,
        ])->assertOk()->json('data.id');
        $file = "/api/v1/attachments/$attachment";

        Sanctum::actingAs($nonmember);
        $this->get($file)->assertForbidden();
        $this->upload($url, 'nonmember.pdf')->assertForbidden();
        $this->deleteJson($file, ['reason' => 'Denied'])->assertForbidden();
        Sanctum::actingAs($foreign);
        $this->get($file)->assertNotFound();
        $this->upload($url, 'foreign.pdf')->assertNotFound();

        Sanctum::actingAs($b);
        $this->get($file)->assertOk()->assertDownload('daily.pdf');
        $receipt = $this->upload("/api/v1/expenses/$expense/attachments", 'receipt.pdf')
            ->assertOk()->json('data.0.id');
        $this->assertDatabaseHas('attachments', ['id' => $receipt, 'created_by' => $b->id]);
        $log->update(['is_locked' => true]);
        $this->get($file)->assertOk();
        $this->upload($url, 'locked.pdf')->assertStatus(500);
        $this->deleteJson("/api/v1/attachments/$receipt", ['reason' => 'Locked'])->assertStatus(500);
        $log->update(['is_locked' => false]);
        $this->deleteJson("/api/v1/attachments/$receipt", ['reason' => 'Remove'])->assertNoContent();
        $this->assertSoftDeleted('attachments', ['id' => $receipt]);

        Sanctum::actingAs($admin);
        $adminExpense = $this->postJson('/api/v1/expenses', [
            'construction_site_id' => $log->construction_site_id, 'title' => 'Admin', 'amount' => 20, 'date' => '2026-02-01',
        ])->assertOk()->json('data.id');
        $adminFile = $this->upload("/api/v1/expenses/$adminExpense/attachments", 'admin.pdf')
            ->assertOk()->json('data.0.id');
        $this->get("/api/v1/attachments/$adminFile")->assertOk();
        Sanctum::actingAs($b);
        $this->get("/api/v1/attachments/$adminFile")->assertForbidden();
        $this->upload("/api/v1/expenses/$adminExpense/attachments", 'denied.pdf')->assertForbidden();

        Expense::findOrFail($expense)->delete();
        $orphan = Attachment::withTrashed()->findOrFail($receipt);
        $orphan->restore();
        $this->get("/api/v1/attachments/$receipt")->assertNotFound();
    }

    private function upload(string $url, string $name)
    {
        return $this->post($url, [
            'attachments' => [UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%%EOF\n")],
        ], ['Accept' => 'application/json']);
    }

    private function sharedLog(): array
    {
        $log = DailyLog::factory()->create(['date' => '2026-02-01']);
        $a = Worker::factory()->for($log->company)->siteManager()->create();
        $b = Worker::factory()->for($log->company)->siteManager()->create();
        $nonmember = Worker::factory()->for($log->company)->siteManager()->create();
        $admin = Worker::factory()->for($log->company)->admin()->create();
        $log->siteManagers()->attach([$a->id, $b->id]);

        return [$log, $a, $b, $nonmember, $admin];
    }
}
