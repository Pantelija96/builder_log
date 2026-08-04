<?php

namespace App\Actions\Note;

use App\Actions\BaseAction;
use App\DTO\Note\UpdateNoteData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Note;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class UpdateNoteAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        DailyLog $dailyLog,
        Note $note,
        UpdateNoteData $data,
        Worker $currentWorker,
        ?string $reason,
    ): Note {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        return $this->transaction(function () use (
            $note,
            $data,
            $currentWorker,
            $reason
        ) {

            $oldValues = $note->getRawOriginal();

            $note->update([
                'note' => $data->note,
                'notify_admin' => $data->notifyAdmin,
            ]);

            $this->logging->audit(
                actor: $currentWorker,
                subject: $note,
                event: LogEvent::NOTE_UPDATED,
                oldValues: $oldValues,
                newValues: $note->fresh()->getAttributes(),
                reason: $reason,
            );

            return $note->fresh([
                'creator',
                'siteManager',
                'attachments',
            ]);
        });
    }
}
