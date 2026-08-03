<?php

namespace App\Actions\DailyLog;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\DailyLog\CreateDailyLogData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use Illuminate\Support\Facades\DB;

class CreateDailyLogAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {
    }

    public function execute(Worker $worker, CreateDailyLogData $data): DailyLog {
        if (! $worker->constructionSites()
            ->whereKey($data->constructionSiteId)
            ->exists())
        {
            throw new BusinessException('You are not assigned to this construction site.');
        }

        if (DailyLog::query()
                ->where('construction_site_id', $data->constructionSiteId)
                ->whereDate('date', $data->date)
                ->exists()
        ) {
            throw new BusinessException('Daily log already exists for this construction site.');
        }

        return $this->transaction(function () use ($worker, $data) {

            $dailyLog = DailyLog::create([
                'company_id' => $data->companyId,
                'construction_site_id' => $data->constructionSiteId,
                'site_manager_id' => $data->siteManagerId,
                'date' => $data->date,
            ]);

            if (! empty($data->attachments)) {
                $this->uploadAttachmentsAction->execute(
                    attachable: $dailyLog,
                    files: $data->attachments,
                    worker: $worker,
                );
            }

            $this->logging->activity(
                actor: $worker,
                subject: $dailyLog,
                event: LogEvent::DAILY_LOG_CREATED,
            );

            return $dailyLog;
        });
    }
}
