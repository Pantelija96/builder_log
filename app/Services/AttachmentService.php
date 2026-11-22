<?php

namespace App\Services;

use App\Actions\Attachment\DeleteAttachmentAction;
use App\Actions\Attachment\UploadAttachmentsAction;
use App\Contracts\HasAttachments;
use App\Models\Attachment;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\Storage\AttachmentStorageService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly AttachmentStorageService $storage,
        private readonly DeleteAttachmentAction $deleteAttachmentAction,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {}

    /**
     * @param  iterable<UploadedFile>  $files
     */
    public function uploadMany(HasAttachments&Model $attachable, iterable $files, Worker $worker): Collection
    {
        $this->authorizeOwner($attachable, $worker, writing: true);

        return $this->uploadAttachmentsAction->execute($attachable, $files, $worker);
    }

    public function download(Attachment $attachment, Worker $worker): StreamedResponse
    {
        $this->authorizeAttachment($attachment, $worker, writing: false);

        return $this->storage->download($attachment);
    }

    public function delete(Attachment $attachment, Worker $worker, string $reason): void
    {
        $this->authorizeAttachment($attachment, $worker, writing: true);

        $this->deleteAttachmentAction->execute($attachment, $worker, $reason);
    }

    private function authorizeAttachment(Attachment $attachment, Worker $worker, bool $writing): void
    {
        abort_unless($attachment->company_id === $worker->company_id, 404);

        $owner = $attachment->attachable;
        abort_unless($owner instanceof HasAttachments && $owner->attachmentDailyLogId() === $attachment->daily_log_id, 404);

        $this->authorizeOwner($owner, $worker, $writing);
    }

    private function authorizeOwner(HasAttachments&Model $owner, Worker $worker, bool $writing): void
    {
        abort_unless($owner->attachmentCompanyId() === $worker->company_id, 404);

        if ($owner instanceof Expense && $owner->daily_log_id === null) {
            abort_unless($worker->isAdmin() && $owner->site_manager_id === null, 403);

            return;
        }

        $dailyLog = $owner instanceof DailyLog ? $owner : $owner->dailyLog;
        abort_unless($dailyLog instanceof DailyLog, 404);

        $this->ensureCanModify($dailyLog, $worker);

        if ($writing) {
            $this->ensureEditable($dailyLog);
        }
    }
}
