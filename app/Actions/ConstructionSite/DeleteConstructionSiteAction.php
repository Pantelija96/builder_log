<?php

namespace App\Actions\ConstructionSite;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteConstructionSiteAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        ConstructionSite $constructionSite,
        Worker $currentWorker,
        string $reason,
    ): void {
        if ($constructionSite->company_id !== $currentWorker->company_id) {
            throw new BusinessException(
                'Construction site not found.'
            );
        }

        $this->ensureTodayDailyLogIsClosed(
            constructionSite: $constructionSite,
        );

        $this->transaction(function () use (
            $constructionSite,
            $currentWorker,
            $reason,
        ) {
            $oldValues = $constructionSite->getAttributes();

            $this->logging->activity(
                actor: $currentWorker,
                subject: $constructionSite,
                event: LogEvent::CONSTRUCTION_SITE_DELETED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $constructionSite,
                event: LogEvent::CONSTRUCTION_SITE_DELETED,
                oldValues: $oldValues,
                reason: $reason,
            );

            $constructionSite->delete();
        });
    }

    private function ensureTodayDailyLogIsClosed(
        ConstructionSite $constructionSite,
    ): void {
        $hasOpenDailyLogToday = DailyLog::query()
            ->where(
                'construction_site_id',
                $constructionSite->id,
            )
            ->whereDate('date', today())
            ->where('is_locked', false)
            ->exists();

        if ($hasOpenDailyLogToday) {
            throw new BusinessException(
                'Construction site cannot be deleted while today\'s daily log is open. Close the daily log first.'
            );
        }
    }
}
