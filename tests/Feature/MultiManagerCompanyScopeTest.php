<?php

namespace Tests\Feature;

use App\Models\CashAdvance;
use App\Models\DailyLog;
use App\Models\Document;
use App\Models\Task;
use App\Models\Worker;
use App\Services\WorkerPushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiManagerCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_lists_and_direct_ids_use_the_authenticated_company(): void
    {
        $this->mock(WorkerPushNotificationService::class)->shouldNotReceive('send');
        $local = DailyLog::factory()->create(['date' => today()]);
        $outside = DailyLog::factory()->create(['date' => today()]);
        $admin = Worker::factory()->for($local->company)->admin()->create();
        $outsider = $outside->siteManagers()->sole();
        $outside->constructionSite->siteManagers()->attach($outsider);
        $localManager = $local->siteManagers()->sole();
        $local->constructionSite->siteManagers()->attach($localManager);
        $task = Task::create([
            'company_id' => $outside->company_id, 'title' => 'Foreign task', 'created_by' => $outsider->id,
        ]);
        $advance = CashAdvance::create([
            'company_id' => $outside->company_id, 'site_manager_id' => $outsider->id, 'created_by' => $outsider->id,
            'amount' => 50, 'date' => today(),
        ]);
        $document = Document::create([
            'company_id' => $outside->company_id, 'construction_site_id' => $outside->construction_site_id,
            'uploaded_by' => $outsider->id, 'name' => 'Foreign file',
            'path' => 'tests/foreign.pdf', 'mime_type' => 'application/pdf', 'original_name' => 'foreign.pdf', 'size' => 10,
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/workers')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/workers?company_id='.$outside->company_id)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/workers/{$outsider->id}")->assertNotFound();
        $this->getJson('/api/v1/construction-sites')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/site-managers/{$outsider->id}/assigned-sites")->assertNotFound();
        $this->getJson("/api/v1/site-managers/{$outsider->id}/overall")->assertNotFound();
        $this->getJson("/api/v1/construction-sites/{$outside->construction_site_id}/site-managers")->assertNotFound();
        $this->postJson("/api/v1/construction-sites/{$local->construction_site_id}/site-managers/{$outsider->id}")->assertNotFound();
        $this->deleteJson("/api/v1/construction-sites/{$outside->construction_site_id}/site-managers/{$outsider->id}")->assertNotFound();
        $this->postJson("/api/v1/daily-logs/{$outside->id}/lock")->assertNotFound();
        $this->postJson("/api/v1/daily-logs/{$outside->id}/unlock", ['reason' => 'Test'])->assertNotFound();
        $this->getJson('/api/v1/tasks')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/tasks', ['title' => 'Test', 'construction_site_id' => $outside->construction_site_id])->assertNotFound();
        $this->deleteJson("/api/v1/tasks/{$task->id}", ['reason' => 'Test'])->assertNotFound();
        $this->getJson('/api/v1/cash-advances')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/cash-advances', [
            'site_manager_id' => $outsider->id, 'amount' => 25, 'date' => today()->toDateString(),
        ])->assertNotFound();
        $this->deleteJson("/api/v1/cash-advances/{$advance->id}", ['reason' => 'Test'])->assertNotFound();
        $this->getJson("/api/v1/documents/{$document->id}/download")->assertNotFound();
        $this->deleteJson("/api/v1/documents/{$document->id}", ['reason' => 'Test'])->assertNotFound();

        $localDocument = Document::create([
            'company_id' => $local->company_id, 'construction_site_id' => $local->construction_site_id,
            'uploaded_by' => $admin->id, 'name' => 'Local file', 'original_name' => 'map.pdf',
            'path' => 'tests/map.pdf', 'mime_type' => 'application/pdf', 'size' => 12,
        ]);
        $body = ['construction_site_id' => $local->construction_site_id, 'name' => 'Updated map'];
        $url = "/api/v1/documents/{$localDocument->id}";
        $this->patchJson($url, $body + ['site_manager_id' => $outsider->id])->assertNotFound();
        $this->patchJson($url, $body + ['site_manager_id' => $admin->id])->assertUnprocessable();
        $this->patchJson($url, $body + ['site_manager_id' => null])->assertOk();

        Sanctum::actingAs($localManager);
        $this->postJson("/api/v1/construction-sites/{$local->construction_site_id}/site-managers/{$localManager->id}")
            ->assertForbidden();
    }
}
