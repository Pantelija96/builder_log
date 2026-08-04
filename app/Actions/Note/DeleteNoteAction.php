<?php

namespace App\Actions\Note;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Note;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class DeleteNoteAction extends BaseAction
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
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        $this->transaction(function () use (
            $note,
            $currentWorker,
            $reason
        ) {

            $this->logging->audit(
                actor: $currentWorker,
                subject: $note,
                event: LogEvent::NOTE_DELETED,
                oldValues: $note->getRawOriginal(),
                reason: $reason,
            );

            $note->delete();
        });
    }
}
