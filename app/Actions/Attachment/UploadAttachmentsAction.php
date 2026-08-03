<?php

namespace App\Actions\Attachment;

use App\Contracts\HasAttachments;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class UploadAttachmentsAction
{
    public function __construct(
        private readonly UploadAttachmentAction $uploadAttachmentAction,
    ) {
    }

    /**
     * @param iterable<UploadedFile> $files
     */
    public function execute(HasAttachments&Model $attachable, iterable $files, Worker $worker,): Collection {

        $attachments = collect();
        $files = collect($files)->filter();

        foreach ($files as $file) {
            $attachments->push(
                $this->uploadAttachmentAction->execute(
                    attachable: $attachable,
                    file: $file,
                    worker: $worker,
                )
            );
        }

        return $attachments;
    }
}
