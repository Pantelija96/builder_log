<?php

namespace App\Actions\Expense;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\Expense\CreateExpenseData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class CreateExpenseAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {
    }

    public function execute(DailyLog $dailyLog, CreateExpenseData $data, Worker $currentWorker,): Expense {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        return $this->transaction(function () use ($dailyLog, $data, $currentWorker) {

            $expense = Expense::create([
                'company_id' => $dailyLog->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $dailyLog->site_manager_id,
                'title' => $data->title,
                'description' => $data->description,
                'amount' => $data->amount,
                'date' => $dailyLog->date,
                'created_by' => $currentWorker->id,
            ])->refresh();

            if (! empty($data->attachments)) {

                $this->uploadAttachmentsAction->execute(
                    attachable: $expense,
                    files: $data->attachments,
                    worker: $currentWorker,
                );

            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $expense,
                event: LogEvent::EXPENSE_CREATED,
            );

            return $expense;
        });
    }
}
