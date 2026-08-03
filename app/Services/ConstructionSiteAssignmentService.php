<?php

namespace App\Services;

use App\Actions\ConstructionSiteAssignment\AssignSiteManagerAction;
use App\Actions\ConstructionSiteAssignment\GetAssignedSiteManagersAction;
use App\Actions\ConstructionSiteAssignment\RemoveSiteManagerAction;
use App\Models\ConstructionSite;
use App\Models\Worker;

class ConstructionSiteAssignmentService
{
    public function __construct(
        private readonly AssignSiteManagerAction $assignAction,
        private readonly RemoveSiteManagerAction $removeAction,
        private readonly GetAssignedSiteManagersAction $getAssignedAction,
    ) {}

    public function getAssigned(ConstructionSite $constructionSite,){
        return $this->getAssignedAction->execute($constructionSite);
    }

    public function assign(ConstructionSite $site, Worker $worker,): void {
        $this->assignAction->execute($site, $worker);
    }

    public function remove(ConstructionSite $site, Worker $worker,): void {
        $this->removeAction->execute($site, $worker);
    }
}
