<?php

namespace App\Actions\Document;

use App\Actions\BaseAction;
use App\DTO\Document\UpdateDocumentData;
use App\Enums\LogEvent;
use App\Models\Document;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class UpdateDocumentAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        Document $document,
        UpdateDocumentData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): Document {

        return $this->transaction(function () use (
            $document,
            $data,
            $currentWorker,
            $reason,
        ) {

            $oldValues = $document->getRawOriginal();

            $document->update([
                'construction_site_id' => $data->constructionSiteId,
                'site_manager_id' => $data->siteManagerId,
                'name' => $data->name,
                'type' => $data->type,
            ]);

            $this->logging->audit(
                actor: $currentWorker,
                subject: $document,
                event: LogEvent::DOCUMENT_UPDATED,
                oldValues: $oldValues,
                newValues: $document->fresh()->getAttributes(),
                reason: $reason,
            );

            return $document->fresh([
                'constructionSite',
                'siteManager',
                'uploader',
            ]);
        });
    }
}
