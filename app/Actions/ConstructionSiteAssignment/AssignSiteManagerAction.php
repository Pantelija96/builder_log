<?php

namespace App\Actions\ConstructionSiteAssignment;

use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\Worker;

class AssignSiteManagerAction
{
    public function execute(ConstructionSite $constructionSite, Worker $worker,): void {

        if ($constructionSite->siteManagers()->whereKey($worker->id)->exists()) {
            throw new BusinessException(
                'Site manager is already assigned to this construction site.'
            );
        }

        $constructionSite->siteManagers()->attach($worker);
    }
}
