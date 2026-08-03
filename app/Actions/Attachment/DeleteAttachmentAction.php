<?php

namespace App\Actions\Attachment;

use App\Contracts\HasAttachments;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\Attachment;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\Storage\AttachmentStorageService;
use Illuminate\Database\Eloquent\Model;

class DeleteAttachmentAction
{
    public function __construct(
        private readonly AttachmentStorageService $storage,
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(Attachment $attachment, Worker $worker, string $reason,): void {
        if (
            $attachment->company_id !== $worker->company_id
        ) {
            throw new BusinessException(
                __('Attachment not found.')
            );
        }

        $oldValues = $attachment->getAttributes();

        $this->logging->activity(
            actor: $worker,
            subject: $attachment,
            event: LogEvent::ATTACHMENT_DELETED,
        );

        $this->logging->audit(
            actor: $worker,
            subject: $attachment,
            event: LogEvent::ATTACHMENT_DELETED,
            oldValues: $oldValues,
            reason: $reason,
        );

        $this->storage->delete($attachment);

        $attachment->delete();
    }
}
