<?php

namespace App\Actions\Worker;

use App\Actions\BaseAction;
use App\DTO\Worker\UpdateWorkerData;
use App\Enums\LogEvent;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class UpdateWorkerAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,)
    {}

    public function execute(
        Worker $worker,
        UpdateWorkerData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): Worker {
        return $this->transaction(function () use (
            $worker,
            $data,
            $currentWorker,
            $reason,
        ) {

            $oldValues = $worker->getAttributes();
            $values = [];

            if (in_array('first_name', $data->providedFields, true))
            {
                $values['first_name'] = $data->firstName;
            }

            if (in_array('last_name', $data->providedFields, true))
            {
                $values['last_name'] = $data->lastName;
            }

            if (in_array('phone', $data->providedFields, true))
            {
                $values['phone'] = $data->phone;
            }

            if (in_array('role', $data->providedFields, true))
            {
                $values['role'] = $data->role;
            }

            if (in_array('username', $data->providedFields, true))
            {
                $values['username'] = $data->username;
            }

            if (in_array('password', $data->providedFields, true))
            {
                $values['password'] = $data->password;
            }

            if (in_array('email', $data->providedFields, true))
            {
                $values['email'] = $data->email;
            }

            if (in_array('is_active', $data->providedFields, true))
            {
                $values['is_active'] = $data->isActive;
            }

            $worker->update($values);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $worker,
                event: LogEvent::WORKER_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $worker,
                event: LogEvent::WORKER_UPDATED,
                oldValues: $oldValues,
                newValues: $worker->fresh()->getAttributes(),
                reason: $reason,
            );

            return $worker->fresh([
                'company',
            ]);
        });
    }
}
