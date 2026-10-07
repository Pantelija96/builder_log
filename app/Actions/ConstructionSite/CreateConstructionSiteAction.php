<?php

namespace App\Actions\ConstructionSite;

use App\Actions\BaseAction;
use App\DTO\ConstructionSite\CreateConstructionSiteData;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\Worker;

class CreateConstructionSiteAction extends BaseAction
{
    public function execute(CreateConstructionSiteData $data, Worker $currentWorker,): ConstructionSite {
        return $this->transaction(function () use ($data, $currentWorker,) {
            $this->ensureSiteManagersBelongToCompany(
                siteManagerIds: $data->siteManagerIds,
                companyId: $currentWorker->company_id,
            );

            $constructionSite = ConstructionSite::create([
                'company_id' => $currentWorker->company_id,
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
}
