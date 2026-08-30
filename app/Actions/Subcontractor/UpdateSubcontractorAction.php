<?php

namespace App\Actions\Subcontractor;

use App\Actions\BaseAction;
use App\DTO\Subcontractor\UpdateSubcontractorData;
use App\Models\Subcontractor;
use App\Models\Worker;

class UpdateSubcontractorAction extends BaseAction
{
    public function execute(
        Subcontractor $subcontractor,
        UpdateSubcontractorData $data,
        Worker $currentWorker,
    ): Subcontractor {
        return $this->transaction(function () use (
            $subcontractor,
            $data,
            $currentWorker,
        ) {
            $subcontractor->update([
                'name' => $data->name,
                'description' => $data->description,
                'pib' => $data->pib,
                'address' => $data->address,
                'phone' => $data->phone,
                'email' => $data->email,
                'contact_first_name' => $data->contactFirstName,
                'contact_last_name' => $data->contactLastName,
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'is_active' => $data->isActive,
            ]);

            return $subcontractor->fresh([
                'company',
            ]);
        });
    }
}
