<?php

namespace App\Actions\WorkerPayment;

use App\Actions\BaseAction;
use App\DTO\WorkerPayment\CreateWorkerPaymentData;
use App\Models\Worker;
use App\Models\WorkerPayment;

class CreateWorkerPaymentAction extends BaseAction
{
    public function execute(
        CreateWorkerPaymentData $data,
        Worker $currentWorker,
    ): WorkerPayment {
        return $this->transaction(function () use ($data, $currentWorker) {

            $worker = Worker::query()
                ->whereKey($data->workerId)
                ->where('company_id', $currentWorker->company_id)
                ->firstOrFail();

            return WorkerPayment::create([
                'company_id' => $currentWorker->company_id,
                'worker_id' => $worker->id,
                'amount' => $data->amount,
                'date' => $data->date,
                'note' => $data->note,
                'created_by' => $currentWorker->id,
            ])->refresh();
        });
    }
}
