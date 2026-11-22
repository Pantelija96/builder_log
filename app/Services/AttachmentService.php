<?php

namespace App\Services;

use App\Actions\Attachment\DeleteAttachmentAction;
use App\Actions\Attachment\UploadAttachmentsAction;
use App\Contracts\HasAttachments;
use App\Models\Attachment;
use App\Models\Worker;
use App\Services\Storage\AttachmentStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    public function __construct(
        private readonly AttachmentStorageService $storage,
        private readonly DeleteAttachmentAction $deleteAttachmentAction,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {
    }

    /**
     * @param  iterable<UploadedFile>  $files
     */
    public function uploadMany(HasAttachments&Model $attachable, iterable $files, Worker $worker): Collection
    {
        return $this->uploadAttachmentsAction->execute($attachable, $files, $worker);
    }

    public function download(Attachment $attachment,): StreamedResponse
    {
        return $this->storage->download($attachment);
    }

    public function delete(Attachment $attachment, Worker $worker, string $reason,): void {
        $this->deleteAttachmentAction->execute(
            attachment: $attachment,
            worker: $worker,
            reason: $reason,
        );
    }
}
