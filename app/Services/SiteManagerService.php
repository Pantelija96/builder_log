<?php

namespace App\Services;

use App\Actions\SiteManager\GetAssignedConstructionSitesAction;
use App\Actions\SiteManager\GetSiteManagerOverallAction;
use App\DTO\SiteManager\GetSiteManagerOverallData;
use App\Models\Worker;
use Illuminate\Support\Collection;

class SiteManagerService
{
    public function __construct(
        private readonly GetSiteManagerOverallAction $getSiteManagerOverallAction,
        private readonly GetAssignedConstructionSitesAction $getAssignedConstructionSitesAction,
    )
    {}

    public function getOverall(int $siteManagerId, GetSiteManagerOverallData $data, Worker $worker): Collection
    {
        return $this->getSiteManagerOverallAction->execute(
            siteManagerId: $siteManagerId,
            data: $data,
            worker: $worker,
        );
    }

    public function getAssignedConstructionSites(Worker $siteManager,): Collection
    {
        return $this->getAssignedConstructionSitesAction->execute(
            siteManager: $siteManager,
        );
    }
}
