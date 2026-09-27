<?php

namespace App\Actions\Expense;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\Expense\CreateAdminExpenseData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\Expense;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class CreateAdminExpenseAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {
    }

    public function execute(
        CreateAdminExpenseData $data,
        Worker $currentWorker,
    ): Expense {
        if (! $currentWorker->isAdmin()) {
            throw new BusinessException(
                'Only administrators can create admin expenses.'
            );
        }

        $constructionSite = ConstructionSite::query()
            ->where('company_id', $currentWorker->company_id)
            ->findOrFail($data->constructionSiteId);

        return $this->transaction(
            function () use ($data, $currentWorker, $constructionSite) {

                $expense = Expense::create([
                    'company_id' => $currentWorker->company_id,
                    'daily_log_id' => null,
                    'construction_site_id' => $constructionSite->id,
                    'site_manager_id' => null,

                    'title' => $data->title,
                    'description' => $data->description,
                    'amount' => $data->amount,
                    'date' => $data->date->toDateString(),

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
            }
        );
    }
}
