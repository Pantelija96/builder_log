<?php

namespace App\Actions\Document;

use App\Actions\BaseAction;
use App\DTO\Document\CreateDocumentData;
use App\Enums\LogEvent;
use App\Models\Document;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CreateDocumentAction extends BaseAction
{
    public function __construct(
        private readonly UploadDocumentAction $uploadDocumentAction,
        private readonly LoggingService $logging,
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
}
