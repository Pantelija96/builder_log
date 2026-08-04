<?php

namespace App\Actions\Note;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\Note\CreateNoteData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Note;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class CreateNoteAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {
    }

    public function execute(
        DailyLog $dailyLog,
        CreateNoteData $data,
        Worker $currentWorker,
    ): Note {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        return $this->transaction(function () use (
            $dailyLog,
            $data,
            $currentWorker
        ) {

            $note = Note::create([
                'company_id' => $dailyLog->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $dailyLog->site_manager_id,

                'note' => $data->note,
                'notify_admin' => $data->notifyAdmin,

                'date' => $dailyLog->date,
                'created_by' => $currentWorker->id,
            ])->refresh();

            if (! empty($data->attachments)) {

                $this->uploadAttachmentsAction->execute(
                    attachable: $note,
                    files: $data->attachments,
                    worker: $currentWorker,
                );

            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $note,
                event: LogEvent::NOTE_CREATED,
            );

            return $note;
        });
    }
}
