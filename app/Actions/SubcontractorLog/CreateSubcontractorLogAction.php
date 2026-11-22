<?php

namespace App\Actions\SubcontractorLog;

use App\Actions\BaseAction;
use App\DTO\SubcontractorLog\CreateSubcontractorLogData;
use App\Exceptions\BusinessException;
use App\Models\DailyLog;
use App\Models\SubcontractorLog;
use App\Models\Subcontractor;
use App\Models\Worker;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class CreateSubcontractorLogAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function execute(DailyLog $dailyLog, CreateSubcontractorLogData $data, Worker $currentWorker,): SubcontractorLog {

        $this->ensureCanModify($dailyLog, $currentWorker);
        $this->ensureEditable($dailyLog);
        $this->ensureManagerAttribution($currentWorker);
        Subcontractor::query()->where('company_id', $dailyLog->company_id)->findOrFail($data->subcontractorId);

        return $this->transaction(function () use ($dailyLog, $data, $currentWorker) {

            if (
                $dailyLog->subcontractorLogs()
                    ->where('subcontractor_id', $data->subcontractorId)
                    ->exists()
            ) {
                throw new BusinessException(
                    'Subcontractor has already been added to this daily log.'
                );
            }

            return SubcontractorLog::create([
                'company_id' => $dailyLog->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $currentWorker->id,

                'subcontractor_id' => $data->subcontractorId,
                'worker_count' => $data->workerCount,

                'started_at' => $data->startedAt,
                'finished_at' => $data->finishedAt,
                'note' => $data->note,

                'date' => $dailyLog->date,

                'created_by' => $currentWorker->id,
            ]);
        });
    }
}
