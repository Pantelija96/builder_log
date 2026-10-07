<?php

namespace App\Services;

use App\Actions\ConstructionSite\CreateConstructionSiteAction;
use App\Actions\ConstructionSite\DeleteConstructionSiteAction;
use App\Actions\ConstructionSite\UpdateConstructionSiteAction;
use App\DTO\ConstructionSite\CreateConstructionSiteData;
use App\DTO\ConstructionSite\UpdateConstructionSiteData;
use App\DTO\Requests\GetConstructionSitesData;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\QueryFilters\ConstructionSiteFilter;
use Illuminate\Database\Eloquent\Collection;

class ConstructionSiteService
{
    public function __construct(
        private readonly CreateConstructionSiteAction $createAction,
        private readonly UpdateConstructionSiteAction $updateAction,
        private readonly DeleteConstructionSiteAction $deleteAction,
    ) {
    }

    public function getAll(Worker $worker, GetConstructionSitesData $data,): Collection {
        $query = ConstructionSite::query();

        if ($worker->isSiteManager()) {
            $query->whereHas(
                'siteManagers',
                function ($q) use ($worker) {
                    $q->whereKey($worker->id);
                }
            );
        }

        $query->with([
            'company',
            'todayDailyLog',
            'siteManagers',
        ]);

        $query = (new ConstructionSiteFilter($data))
            ->apply($query);

        $constructionSites = $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();

        $constructionSites->each(
            function (
                ConstructionSite $constructionSite,
            ) use ($worker) {
                $dailyLog = $constructionSite->todayDailyLog;

                $constructionSite->can_select =
                    $dailyLog === null
                    || $dailyLog->site_manager_id === $worker->id;
            }
        );

        return $constructionSites;
    }

    public function create(CreateConstructionSiteData $data, Worker $currentWorker,): ConstructionSite {
        return $this->createAction->execute(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function update(ConstructionSite $constructionSite, UpdateConstructionSiteData $data, Worker $currentWorker,): ConstructionSite {
        return $this->updateAction->execute(
            constructionSite: $constructionSite,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function delete(ConstructionSite $constructionSite, Worker $currentWorker, string $reason,): void {
        $this->deleteAction->execute(
            constructionSite: $constructionSite,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }
}
