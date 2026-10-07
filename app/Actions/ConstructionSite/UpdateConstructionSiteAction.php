<?php

namespace App\Actions\ConstructionSite;

use App\Actions\BaseAction;
use App\DTO\ConstructionSite\UpdateConstructionSiteData;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;

class UpdateConstructionSiteAction extends BaseAction
{
    public function execute(ConstructionSite $constructionSite, UpdateConstructionSiteData $data, Worker $currentWorker,): ConstructionSite {
        return $this->transaction(function () use ($constructionSite, $data, $currentWorker,) {
            if ($constructionSite->company_id !== $currentWorker->company_id) {
                abort(404);
            }

            $this->ensureSiteManagersBelongToCompany(
                siteManagerIds: $data->siteManagerIds,
                companyId: $currentWorker->company_id,
            );

            $this->ensureSiteManagersCanBeChanged(
                constructionSite: $constructionSite,
                newSiteManagerIds: $data->siteManagerIds,
            );

            $constructionSite->update([
                'name' => $data->name,
                'description' => $data->description,
                'address' => $data->address,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'status' => $data->status,
            ]);

            $constructionSite
                ->siteManagers()
                ->sync($data->siteManagerIds);

            return $constructionSite->fresh([
                'company',
                'siteManagers',
            ]);
        });
    }

    private function ensureSiteManagersBelongToCompany(array $siteManagerIds, int $companyId,): void {
        if (empty($siteManagerIds)) {
            return;
        }

        $validCount = Worker::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $siteManagerIds)
            ->count();

        if ($validCount !== count($siteManagerIds)) {
            throw new BusinessException(
                'All site managers must belong to the same company.'
            );
        }
    }

    private function ensureSiteManagersCanBeChanged(ConstructionSite $constructionSite, array $newSiteManagerIds,): void {
        $currentIds = $constructionSite
            ->siteManagers()
            ->pluck('workers.id')
            ->sort()
            ->values()
            ->all();

        $newIds = collect($newSiteManagerIds)
            ->sort()
            ->values()
            ->all();

        if ($currentIds === $newIds) {
            return;
        }

        $hasDailyLogToday = DailyLog::query()
            ->where('construction_site_id', $constructionSite->id)
            ->whereDate('date', today())
            ->exists();

        if ($hasDailyLogToday) {
            throw new BusinessException(
                'Site managers cannot be modified while a daily log exists for today.'
            );
        }
    }
}
