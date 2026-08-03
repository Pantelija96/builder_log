<?php

namespace App\Actions\Expense;

use App\Actions\BaseAction;
use App\DTO\Expense\UpdateExpenseData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
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
    ) {
    }

    public function execute(DailyLog $dailyLog, Expense $expense, UpdateExpenseData $data, Worker $currentWorker, ?string $reason): Expense {

        if ($expense->daily_log_id !== $dailyLog->id) {
            throw new BusinessException(
                'Expense does not belong to the specified daily log.'
            );
        }

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        return $this->transaction(function () use ($expense, $data, $currentWorker, $reason) {

            $oldValues = $expense->getAttributes();

            $expense->update([
                'title' => $data->title,
                'description' => $data->description,
                'amount' => $data->amount,
            ]);

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
