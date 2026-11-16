<?php

namespace App\Actions\Supplier;

use App\Actions\BaseAction;
use App\DTO\Supplier\CreateSupplierData;
use App\Models\Supplier;
use App\Models\Worker;

class CreateSupplierAction extends BaseAction
{
    public function execute(
        CreateSupplierData $data,
        Worker $currentWorker,
    ): Supplier {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {
            $supplier = Supplier::create([
                'company_id' =>
                    $currentWorker->company_id,

                'name' =>
                    $data->name,

                'pib' =>
                    $data->pib,

                'address' =>
                    $data->address,

                'phone' =>
                    $data->phone,

                'email' =>
                    $data->email,

                'contact_first_name' =>
                    $data->contactFirstName,

                'contact_last_name' =>
                    $data->contactLastName,

                'contact_email' =>
                    $data->contactEmail,

                'contact_phone' =>
                    $data->contactPhone,

                'is_active' =>
                    $data->isActive,
            ]);

            return $supplier->fresh([
                'company',
            ]);
        });
    }
}
