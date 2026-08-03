<?php

namespace App\Actions\ConstructionSiteAssignment;

use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;

class RemoveSiteManagerAction
{
    public function execute(ConstructionSite $constructionSite, Worker $worker,): void {
        if (DailyLog::query()->where('construction_site_id', $constructionSite->id)->whereDate('date', today())->exists()) {
            throw new BusinessException(
                'Site managers cannot be modified while a daily log exists for today.'
            );
        }
        $constructionSite->siteManagers()->detach($worker);
    }
}
