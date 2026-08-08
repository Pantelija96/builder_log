<?php

namespace App\Actions\Document;

use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\Document;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\Storage\DocumentStorageService;

class DeleteDocumentAction
{
    public function __construct(
        private readonly DocumentStorageService $storage,
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        Document $document,
        Worker $currentWorker,
        string $reason,
    ): void {

        if (
            $document->company_id !== $currentWorker->company_id
        ) {
            throw new BusinessException(
                __('Document not found.')
            );
        }

        $oldValues = $document->getAttributes();

        $this->logging->activity(
            actor: $currentWorker,
            subject: $document,
            event: LogEvent::DOCUMENT_DELETED,
        );

        $this->logging->audit(
            actor: $currentWorker,
            subject: $document,
            event: LogEvent::DOCUMENT_DELETED,
            oldValues: $oldValues,
            reason: $reason,
        );

        $this->storage->delete(
            $document,
        );

        $document->delete();
    }
}
