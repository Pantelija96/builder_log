<?php

namespace App\Services;

use App\DTO\Requests\GetConstructionSitesData;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\QueryFilters\ConstructionSiteFilter;
use Illuminate\Database\Eloquent\Collection;

class ConstructionSiteService
{
    public function getAll(Worker $worker, GetConstructionSitesData $data): Collection
    {
        $query = ConstructionSite::query();

        if ($worker->isSiteManager()) {
            $query->whereHas('siteManagers', function ($q) use ($worker) {
                $q->whereKey($worker->id);
            });
        }

        $query->with(['company', 'todayDailyLog']);

        $query = (new ConstructionSiteFilter($data))->apply($query);

        $constructionSites = $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();

        $constructionSites->each(function (ConstructionSite $constructionSite) use ($worker) {
            $dailyLog = $constructionSite->todayDailyLog;
            $constructionSite->can_select = $dailyLog === null || $dailyLog->site_manager_id === $worker->id;
        });

        return $constructionSites;
    }
}
