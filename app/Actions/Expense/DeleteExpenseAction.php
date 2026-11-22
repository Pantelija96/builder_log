<?php

namespace App\Actions\Expense;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class DeleteExpenseAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(DailyLog $dailyLog, Expense $expense, Worker $currentWorker, string $reason,): void {

        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureDailyLogChild($dailyLog, $expense);
        $this->ensureEditable($dailyLog);

        $this->transaction(function () use ($expense, $currentWorker, $reason) {

            $oldValues = $expense->getAttributes();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $expense,
                event: LogEvent::EXPENSE_DELETED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $expense,
                event: LogEvent::EXPENSE_DELETED,
                oldValues: $oldValues,
                reason: $reason,
            );

            $expense->delete();
        });
    }
}
