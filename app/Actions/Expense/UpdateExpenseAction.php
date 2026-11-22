<?php

namespace App\Actions\Expense;

use App\Actions\Attachment\SynchronizeAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\Expense\UpdateExpenseData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class UpdateExpenseAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
        private readonly SynchronizeAttachmentsAction $synchronizeAttachmentsAction,
    ) {
    }

    public function execute(DailyLog $dailyLog, Expense $expense, UpdateExpenseData $data, Worker $currentWorker, ?string $reason): Expense {

        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $expense);
        $this->ensureEditable($dailyLog);

        return $this->transaction(function () use ($expense, $data, $currentWorker, $reason) {

            $oldValues = $expense->getAttributes();

            $expense->update([
                'title' => $data->title,
                'description' => $data->description,
                'amount' => $data->amount,
            ]);

            $this->synchronizeAttachmentsAction->execute(
                attachable: $expense,
                uploadedFiles: $data->attachments,
                deleteAttachmentIds: $data->deleteAttachments,
                worker: $currentWorker,
                reason: $reason,
            );

            $this->logging->activity(
                actor: $currentWorker,
                subject: $expense,
                event: LogEvent::EXPENSE_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $expense,
                event: LogEvent::EXPENSE_UPDATED,
                oldValues: $oldValues,
                newValues: $expense->getAttributes(),
                reason: $reason
            );

            return $expense->refresh();
        });
    }
}
