<?php

namespace App\Actions\SiteManager;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

class GetAssignedConstructionSitesAction
{
    public function execute(Worker $siteManager): Collection
    {
        return $siteManager
            ->constructionSites()
            ->orderBy('name')
            ->get();
    }
}
