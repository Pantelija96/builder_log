<?php

namespace App\Actions\Attachment;

use App\Contracts\HasAttachments;
use App\Exceptions\BusinessException;
use App\Models\Model;
use App\Models\Worker;

class SynchronizeAttachmentsAction
{
    public function __construct(
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
        private readonly DeleteAttachmentAction $deleteAttachmentAction,
    ) {
    }

    public function execute(
        HasAttachments&\Illuminate\Database\Eloquent\Model $attachable,
        array $uploadedFiles,
        array $deleteAttachmentIds,
        Worker $worker,
        ?string $reason = null,
    ): void {

        if (! empty($deleteAttachmentIds)) {

            $attachments = $attachable
                ->attachments()
                ->whereKey($deleteAttachmentIds)
                ->get();

            if ($attachments->count() !== count($deleteAttachmentIds)) {
                throw new BusinessException(
                    __('One or more attachments do not belong to this record.')
                );
            }

            foreach ($attachments as $attachment) {

                $this->deleteAttachmentAction->execute(
                    attachment: $attachment,
                    worker: $worker,
                    reason: $reason ?? __('Updated parent entity'),
                );

            }
        }

        if (! empty($uploadedFiles)) {

            $this->uploadAttachmentsAction->execute(
                attachable: $attachable,
                files: $uploadedFiles,
                worker: $worker,
            );

        }
    }
}
