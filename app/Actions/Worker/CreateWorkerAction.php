<?php

namespace App\Actions\Worker;

use App\Actions\BaseAction;
use App\DTO\Worker\CreateWorkerData;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\Worker;

class CreateWorkerAction extends BaseAction
{
    public function execute(CreateWorkerData $data, int $companyId,): Worker {
        return $this->transaction(function () use ($data, $companyId,) {
            $this->ensureConstructionSitesAreValid(
                data: $data,
                companyId: $companyId,
            );

            $worker = Worker::create([
                'company_id' => $companyId,
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'phone' => $data->phone,
                'role' => $data->role,
                'username' => $data->username,
                'password' => $data->password,
                'email' => $data->email,
                'is_active' => $data->isActive,
                'hourly_rate' => $data->hourlyRate,
            ]);

            if ($data->role === WorkerRole::SITE_MANAGER) {
                $worker->constructionSites()->sync($data->constructionSiteIds);
            }

            return $worker->fresh([
                'company',
                'constructionSites',
            ]);
        });
    }

    private function ensureConstructionSitesAreValid(CreateWorkerData $data, int $companyId,): void {
        if (empty($data->constructionSiteIds)) {
            return;
        }

        if ($data->role !== WorkerRole::SITE_MANAGER) {
            throw new BusinessException(
                'Construction sites can only be assigned to site managers.'
            );
        }

        $validCount = ConstructionSite::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $data->constructionSiteIds)
            ->count();

        if ($validCount !== count($data->constructionSiteIds)) {
            throw new BusinessException(
                'All construction sites must belong to the same company.'
            );
        }
    }
}
