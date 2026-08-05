<?php

namespace App\Services;

use App\Actions\Attachment\DeleteAttachmentAction;
use App\Actions\Attachment\UploadAttachmentAction;
use App\Actions\Attachment\UploadAttachmentsAction;
use App\Contracts\HasAttachments;
use App\DTO\Attachment\StoredAttachmentData;
use App\Exceptions\BusinessException;
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
    ) {
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
