<?php

namespace App\Actions\Subcontractor;

use App\Actions\BaseAction;
use App\DTO\Subcontractor\CreateSubcontractorData;
use App\Models\Subcontractor;
use App\Models\Worker;

class CreateSubcontractorAction extends BaseAction
{
    public function execute(
        CreateSubcontractorData $data,
        Worker $currentWorker,
    ): Subcontractor {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {
            $subcontractor = Subcontractor::create([
                'company_id' => $currentWorker->company_id,
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
