<?php

namespace App\Actions\Worker;

use App\Actions\BaseAction;
use App\DTO\Worker\CreateWorkerData;
use App\Models\Worker;

class CreateWorkerAction extends BaseAction
{
    public function execute(CreateWorkerData $data, int $companyId,): Worker
    {
        return $this->transaction(function () use (
            $data,
            $companyId,
        ) {

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
            ]);

            return $worker->fresh([
                'company',
            ]);
        });
    }
}
