<?php

namespace App\Actions\Supplier;

use App\Actions\BaseAction;
use App\DTO\Supplier\UpdateSupplierData;
use App\Models\Supplier;
use App\Models\Worker;

class UpdateSupplierAction extends BaseAction
{
    public function execute(
        Supplier $supplier,
        UpdateSupplierData $data,
        Worker $currentWorker,
    ): Supplier {
        return $this->transaction(function () use (
            $supplier,
            $data,
            $currentWorker,
        ) {
            $supplier->update([
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
