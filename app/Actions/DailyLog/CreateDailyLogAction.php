<?php

namespace App\Actions\DailyLog;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\DailyLog\CreateDailyLogData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class CreateDailyLogAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {}

    public function execute(Worker $worker, CreateDailyLogData $data): DailyLog
    {
        abort_unless($worker->isSiteManager() && $worker->is_active, 403);
        $site = ConstructionSite::findOrFail($data->constructionSiteId);
        abort_unless($site->company_id === $worker->company_id, 404);

        $date = today();

        if ($data->date->toDateString() === $date->toDateString()) {
            return $this->select($worker, $site, $data->attachments, $date);
        }

        if (! $worker->constructionSites()->whereKey($data->constructionSiteId)->exists()) {
            throw new BusinessException('You are not assigned to this construction site.');
        }

        if (DailyLog::query()
            ->where('construction_site_id', $site->id)
            ->whereDate('date', $data->date)
            ->exists()
        ) {
            throw new BusinessException('Daily log already exists for this construction site.');
        }

        return $this->transaction(fn () => $this->createLog($worker, $site, $data->date, $data->attachments));
    }

    public function select(Worker $worker, ConstructionSite $site, array $attachments = [], ?CarbonInterface $date = null): DailyLog
    {
        abort_unless($worker->isSiteManager() && $worker->is_active, 403);
        abort_unless($site->company_id === $worker->company_id, 404);

        $date ??= today();
        // A legacy create with uploads must not retry storage side effects.
        $attempts = $attachments === [] ? 5 : 1;

        return DB::transaction(function () use ($worker, $site, $date, $attachments) {
            // Serialize first selection and subsequent joins on an existing row, even before a log exists.
            $site = ConstructionSite::query()->lockForUpdate()->findOrFail($site->id);
            abort_unless($site->company_id === $worker->company_id, 404);
            abort_unless($worker->constructionSites()->whereKey($site->id)->exists(), 403);

            $dailyLog = DailyLog::withTrashed()
                ->where('construction_site_id', $site->id)
                ->whereDate('date', $date)
                ->first();

            if ($dailyLog === null) {
                return $this->createLog($worker, $site, $date, $attachments)->refresh();
            }

            abort_if($dailyLog->trashed(), 409, 'The daily log for this site and date is deleted.');
            abort_unless($dailyLog->company_id === $worker->company_id, 404);
            if ($attachments !== []) {
                // The legacy create endpoint rejected duplicate requests carrying uploads.
                throw new BusinessException('Daily log already exists for this construction site.');
            }

            $this->join($dailyLog, $worker);

            return $dailyLog;
        }, attempts: $attempts);
    }

    private function createLog(Worker $worker, ConstructionSite $site, CarbonInterface $date, array $attachments): DailyLog
    {
        $dailyLog = DailyLog::create([
            'company_id' => $site->company_id,
            'construction_site_id' => $site->id,
            'date' => $date->toDateString(),
        ]);

        $this->join($dailyLog, $worker);

        if ($attachments !== []) {
            $this->uploadAttachmentsAction->execute(
                attachable: $dailyLog,
                files: $attachments,
                worker: $worker,
            );
        }

        $this->logging->activity(
            actor: $worker,
            subject: $dailyLog,
            event: LogEvent::DAILY_LOG_CREATED,
        );

        return $dailyLog;
    }

    private function join(DailyLog $dailyLog, Worker $worker): void
    {
        $now = now();
        DB::table('daily_log_site_managers')->upsert([
            'daily_log_id' => $dailyLog->id,
            'site_manager_id' => $worker->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['daily_log_id', 'site_manager_id'], ['daily_log_id']);
    }
}
