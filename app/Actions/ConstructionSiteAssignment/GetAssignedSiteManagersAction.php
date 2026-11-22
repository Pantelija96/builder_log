<?php

namespace App\Actions\ConstructionSiteAssignment;

use App\Models\ConstructionSite;
use Illuminate\Database\Eloquent\Collection;

class GetAssignedSiteManagersAction
{
    public function execute(ConstructionSite $constructionSite): Collection
    {
        return $constructionSite
            ->siteManagers()
            ->where('workers.company_id', $constructionSite->company_id)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
