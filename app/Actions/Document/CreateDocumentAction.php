<?php

namespace App\Actions\Document;

use App\Actions\BaseAction;
use App\DTO\Document\CreateDocumentData;
use App\Enums\LogEvent;
use App\Models\Document;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;
use Illuminate\Http\UploadedFile;

class CreateDocumentAction extends BaseAction
{
    public function __construct(
        private readonly UploadDocumentAction $uploadDocumentAction,
        private readonly LoggingService $logging,
        private readonly NotificationService $notifications,
        private readonly WorkerPushNotificationService $pushNotifications,
    ) {
    }

    public function execute(
        CreateDocumentData $data,
        UploadedFile $file,
        Worker $currentWorker,
    ): Document {

        return $this->transaction(function () use (
            $data,
            $file,
            $currentWorker,
        ) {

            $stored = $this->uploadDocumentAction->execute(
                $file,
            );

            $document = Document::create([
                'company_id' => $currentWorker->company_id,
                'construction_site_id' => $data->constructionSiteId,
                'site_manager_id' => $data->siteManagerId,
                'name' => pathinfo(
                    $file->getClientOriginalName(),
                    PATHINFO_FILENAME,
                ),
                'type' => $data->type,
                'original_name' => $file->getClientOriginalName(),
                'path' => $stored->path,
                'mime_type' => $stored->mimeType,
                'size' => $stored->size,
                'uploaded_by' => $currentWorker->id,
            ]);

            $this->notifySiteManagers($document);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $document,
                event: LogEvent::DOCUMENT_CREATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $document,
                event: LogEvent::DOCUMENT_CREATED,
            );

            return $document->fresh([
                'constructionSite',
                'siteManager',
                'uploader',
            ]);
        });
    }

    private function notifySiteManagers(
        Document $document,
    ): void {
        $siteManagers = $document
            ->constructionSite
            ->siteManagers()
            ->where('is_active', true)
            ->get();

        foreach ($siteManagers as $manager) {
            $notification = $this->notifications->documentCreated(
                worker: $manager,
                document: $document,
            );

            $this->pushNotifications->send(
                worker: $manager,
                title: $notification->title,
                body: $notification->message,
                data: [
                    'type' => 'document_created',
                    'document_id' => $document->id,
                    'construction_site_id' => $document->construction_site_id,
                    'notification_id' => $notification->id,
                ],
            );
        }
    }
}
