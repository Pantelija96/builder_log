<?php

namespace App\Actions\Attachment;

use App\Enums\LogEvent;
use App\Models\Attachment;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\Storage\AttachmentStorageService;
use Illuminate\Database\Eloquent\Model;
use App\Contracts\HasAttachments;
use Illuminate\Http\UploadedFile;

class UploadAttachmentAction
{
    public function __construct(
        private readonly AttachmentStorageService $storage,
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(HasAttachments&Model $attachable, UploadedFile $file, Worker $worker,): Attachment {

        $stored = $this->storage->store($file);

        $attachment = $attachable
            ->attachments()
            ->create([
                'company_id' => $attachable->attachmentCompanyId(),
                'daily_log_id' => $attachable->attachmentDailyLogId(),
                'name' => $stored->name,
                'original_name' => $file->getClientOriginalName(),
                'extension' => $file->extension(),
                'path' => $stored->path,
                'mime_type' => $stored->mimeType,
                'size' => $stored->size,
                'created_by' => $worker->id,
            ]);

        $this->logging->activity(
            actor: $worker,
            subject: $attachment,
            event: LogEvent::ATTACHMENT_UPLOADED,
        );

        return $attachment;
    }
}
