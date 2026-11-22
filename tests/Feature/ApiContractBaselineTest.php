<?php

namespace Tests\Feature;

use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiContractBaselineTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        // Never use the project's configured/live database, even with cached configuration.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
    }

    public function test_me_preserves_the_envelope_and_conditional_relations(): void
    {
        $worker = Worker::factory()->create(['hourly_rate' => 125.5]);
        Sanctum::actingAs($worker);

        $response = $this->getJson('/api/v1/me');
        $response->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.id', $worker->id)
            ->assertJsonPath('data.company.id', $worker->company_id)
            ->assertJsonPath('data.hourly_rate', '125.50')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonMissingPath('data.construction_sites');

        $this->assertSame(['success', 'message', 'data'], array_keys($response->json()));
    }

    public function test_daily_log_detail_and_list_keep_the_current_nested_shapes(): void
    {
        $log = DailyLog::factory()->create();
        Sanctum::actingAs($log->siteManagers()->firstOrFail());

        $this->getJson("/api/v1/daily-logs/{$log->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.construction_site.id', $log->construction_site_id)
            ->assertJsonMissingPath('data.site_manager')
            ->assertJsonPath('data.company.id', $log->company_id)
            ->assertJsonPath('data.is_locked', false)
            ->assertJsonPath('data.locked_by', null)
            ->assertJsonPath('data.attachments', [])
            ->assertJsonMissingPath('data.worker_attendances');

        $this->getJson("/api/v1/daily-logs?construction_site_id={$log->construction_site_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $log->id);
    }

    public function test_worker_payment_uses_a_direct_resource_envelope(): void
    {
        $admin = Worker::factory()->admin()->create();
        $worker = Worker::factory()->create(['company_id' => $admin->company_id]);
        $payment = WorkerPayment::create([
            'company_id' => $admin->company_id,
            'worker_id' => $worker->id,
            'created_by' => $admin->id,
            'amount' => 125.5,
            'date' => '2026-01-15',
        ]);
        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/v1/worker-payments/{$payment->id}");
        $response->assertOk()
            ->assertJsonPath('data.id', $payment->id)
            ->assertJsonPath('data.amount', '125.50')
            ->assertJsonPath('data.date', '2026-01-15')
            ->assertJsonPath('data.worker.id', $worker->id)
            ->assertJsonPath('data.creator.id', $admin->id)
            ->assertJsonMissingPath('data.worker.company');

        $this->assertSame(['data'], array_keys($response->json()));
    }

    public function test_supplier_create_update_and_delete_preserve_status_and_payload(): void
    {
        Sanctum::actingAs(Worker::factory()->admin()->create());

        $created = $this->postJson('/api/v1/suppliers', ['name' => 'Baseline Supplier'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Supplier created successfully.')
            ->assertJsonPath('data.name', 'Baseline Supplier');
        $id = $created->json('data.id');

        $this->patchJson("/api/v1/suppliers/{$id}", ['name' => 'Updated Supplier', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Supplier updated successfully.')
            ->assertJsonPath('data.name', 'Updated Supplier')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/suppliers/{$id}", ['reason' => 'Baseline'])
            ->assertOk()
            ->assertExactJson(['success' => true, 'message' => 'Supplier deleted successfully.', 'data' => null]);
        $this->assertSoftDeleted('suppliers', ['id' => $id]);
    }

    public function test_validation_forbidden_and_binding_errors(): void
    {
        config(['app.debug' => false]);
        $worker = Worker::factory()->create();
        Sanctum::actingAs($worker);

        $this->postJson('/api/v1/suppliers', [])
            ->assertUnprocessable()
            ->assertExactJson([
                'message' => 'The name field is required.',
                'errors' => ['name' => ['The name field is required.']],
            ]);
        $this->postJson('/api/v1/suppliers', ['name' => 'Forbidden Supplier'])
            ->assertForbidden()
            ->assertJsonStructure(['message']);
        $this->getJson('/api/v1/daily-logs/999999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_locked_daily_log_keeps_current_business_exception_response(): void
    {
        config(['app.debug' => false]);
        $log = DailyLog::factory()->locked()->create();
        Sanctum::actingAs($log->siteManagers()->firstOrFail());

        $this->postJson("/api/v1/daily-logs/{$log->id}/lock")
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Server Error']);

        $this->assertTrue($log->fresh()->is_locked);
    }

    public function test_site_manager_assignment_removal_returns_empty_204(): void
    {
        $admin = Worker::factory()->admin()->create();
        $manager = Worker::factory()->siteManager()->create(['company_id' => $admin->company_id]);
        $site = ConstructionSite::factory()->create(['company_id' => $admin->company_id]);
        $site->siteManagers()->attach($manager);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/construction-sites/{$site->id}/site-managers/{$manager->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('construction_site_worker', [
            'construction_site_id' => $site->id,
            'worker_id' => $manager->id,
        ]);
    }

    public function test_attachment_download_preserves_stream_and_filename(): void
    {
        config(['filesystems.attachments.disk' => 'local']);
        Storage::fake('local')->put('contract/sample.txt', 'Baseline download');
        $log = DailyLog::factory()->create();
        $attachment = $log->attachments()->create([
            'company_id' => $log->company_id,
            'daily_log_id' => $log->id,
            'name' => 'sample.txt',
            'original_name' => 'baseline.txt',
            'extension' => 'txt',
            'path' => 'contract/sample.txt',
            'mime_type' => 'text/plain',
            'size' => 17,
            'created_by' => $log->siteManagers()->firstOrFail()->id,
        ]);
        Sanctum::actingAs($log->siteManagers()->firstOrFail());

        $response = $this->get("/api/v1/attachments/{$attachment->id}");
        $response->assertOk()->assertDownload('baseline.txt');
        $this->assertSame('Baseline download', $response->streamedContent());
    }
}
