<?php

namespace Tests\Feature;

use App\Enums\LogEvent;
use App\Models\Attachment;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\Storage\AttachmentStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AttachmentHttpContractTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_CONTENT = "%PDF-1.4\n%%EOF\n";

    private string $disk;

    protected function beforeRefreshingDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
        ]);
        DB::purge('sqlite');
    }

    public function test_daily_log_upload_keeps_input_order_owner_files_and_download_and_delete(): void
    {
        $this->fakeAttachmentDisk();
        $manager = Worker::factory()->siteManager()->create();
        $site = ConstructionSite::factory()->for($manager->company)->create();
        $log = DailyLog::factory()->create([
            'company_id' => $manager->company_id,
            'construction_site_id' => $site->id,
            'site_manager_id' => $manager->id,
        ]);
        Sanctum::actingAs($manager);
        $url = "/api/v1/daily-logs/{$log->id}/attachments";

        $firstResponse = $this->post($url, [
            'attachments' => [UploadedFile::fake()->createWithContent('single.pdf', self::PDF_CONTENT)],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(1, 'data');

        $multipleResponse = $this->post($url, [
            'attachments' => [
                UploadedFile::fake()->createWithContent('second.pdf', self::PDF_CONTENT),
                UploadedFile::fake()->createWithContent('third.pdf', self::PDF_CONTENT),
            ],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(['data'], array_keys($firstResponse->json()));
        $this->assertSame(['second.pdf', 'third.pdf'], array_column($multipleResponse->json('data'), 'name'));
        $this->assertSame([
            'id', 'name', 'extension', 'mime_type', 'size', 'download_url', 'created_at',
        ], array_keys($firstResponse->json('data.0')));
        $this->assertDatabaseCount('attachments', 3);

        $rows = $log->attachments()->orderBy('id')->get();
        $this->assertSame(['single.pdf', 'second.pdf', 'third.pdf'], $rows->pluck('original_name')->all());
        foreach ($rows as $row) {
            $this->assertTrue($row->attachable->is($log));
            $this->assertSame($log->id, $row->daily_log_id);
            $this->assertSame($log->site_manager_id, $row->created_by);
            $this->assertSame($log->company_id, $row->company_id);
            $this->assertSame('contract-attachments/'.$row->name, $row->path);
            Storage::disk($this->disk)->assertExists($row->path);
        }

        $first = $rows->first();
        $this->assertSame(
            route('attachments.download', ['attachment' => $first->id, 'v' => $first->name]),
            $firstResponse->json('data.0.download_url'),
        );
        $attachmentUrl = "/api/v1/attachments/{$first->id}";
        $download = $this->get($attachmentUrl)->assertOk()->assertDownload('single.pdf');
        $this->assertSame(self::PDF_CONTENT, $download->streamedContent());

        $this->deleteJson($attachmentUrl, ['reason' => 'Wrong file'])->assertNoContent();
        $this->assertSoftDeleted('attachments', ['id' => $first->id]);
        Storage::disk($this->disk)->assertMissing($first->path);
        $this->assertDatabaseCount('activity_logs', 4);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $log->site_manager_id,
            'event' => LogEvent::ATTACHMENT_DELETED->value,
            'reason' => 'Wrong file',
        ]);
    }

    public function test_expense_upload_preserves_expense_owner_and_nullable_daily_log_id(): void
    {
        $this->fakeAttachmentDisk();
        $admin = Worker::factory()->admin()->create();
        $log = DailyLog::factory()->for($admin->company)->create();
        $expense = Expense::create([
            'company_id' => $log->company_id,
            'construction_site_id' => $log->construction_site_id,
            'daily_log_id' => null,
            'site_manager_id' => null,
            'created_by' => $admin->id,
            'title' => 'Supplies',
            'date' => $log->date,
            'amount' => 20,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->post("/api/v1/expenses/{$expense->id}/attachments", [
            'attachments' => [UploadedFile::fake()->createWithContent('receipt.pdf', self::PDF_CONTENT)],
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.0.name', 'receipt.pdf');

        $row = Attachment::findOrFail($response->json('data.0.id'));
        $this->assertTrue($row->attachable->is($expense));
        $this->assertNull($row->daily_log_id);
        $this->assertSame($admin->id, $row->created_by);
        $this->assertSame($admin->company_id, $row->company_id);
        Storage::disk($this->disk)->assertExists($row->path);
        $this->assertDatabaseCount('activity_logs', 1);

        config(['app.debug' => false]);
        Sanctum::actingAs(Worker::factory()->admin()->create());
        $this->deleteJson("/api/v1/attachments/{$row->id}", ['reason' => 'Wrong company'])
            ->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
        $this->assertNotSoftDeleted('attachments', ['id' => $row->id]);
        Storage::disk($this->disk)->assertExists($row->path);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_upload_keeps_authentication_and_validation_responses(): void
    {
        $this->fakeAttachmentDisk();
        config(['app.debug' => false]);
        $log = DailyLog::factory()->create();
        $url = "/api/v1/daily-logs/{$log->id}/attachments";

        $this->postJson($url, [])->assertUnauthorized();
        Sanctum::actingAs($log->siteManager);
        $this->postJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('attachments');

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_storage_failure_returns_the_existing_error_without_writing_rows(): void
    {
        $this->fakeAttachmentDisk();
        config(['app.debug' => false]);
        $log = DailyLog::factory()->create();
        Sanctum::actingAs($log->siteManager);
        $url = "/api/v1/daily-logs/{$log->id}/attachments";

        $this->mock(AttachmentStorageService::class)
            ->shouldReceive('store')->once()->andThrow(new RuntimeException('Simulated storage failure'));

        $this->post($url, [
            'attachments' => [UploadedFile::fake()->createWithContent('fail.pdf', self::PDF_CONTENT)],
        ], ['Accept' => 'application/json'])->assertStatus(500)->assertExactJson(['message' => 'Server Error']);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    protected function tearDown(): void
    {
        if (isset($this->disk)) {
            File::deleteDirectory(Storage::disk($this->disk)->path(''));
        }

        parent::tearDown();
    }

    private function fakeAttachmentDisk(): void
    {
        $this->disk = 'attachment-contract-'.bin2hex(random_bytes(8));
        config([
            'filesystems.attachments.disk' => $this->disk,
            'filesystems.attachments.directory' => 'contract-attachments',
        ]);
        Storage::fake($this->disk);
    }
}
