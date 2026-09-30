<?php

namespace App\Actions\WorkerPayment;

use App\Actions\BaseAction;
use App\Exceptions\BusinessException;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\Services\WorkerSalaryService;

class PayWorkerOutstandingAction extends BaseAction
{
    public function __construct(
        private readonly WorkerSalaryService $workerSalaryService,
    ) {
    }

    public function execute(Worker $worker, Worker $currentWorker,): WorkerPayment {
        return $this->transaction(function () use ($worker, $currentWorker,) {
            $worker = Worker::query()
                ->whereKey($worker->id)
                ->where(
                    'company_id',
                    $currentWorker->company_id
                )
                ->lockForUpdate()
                ->first();

            if (! $worker) {
                throw new BusinessException(
                    'Worker not found.'
                );
            }

            $balance = $this->workerSalaryService->getBalance(
                worker: $worker,
                currentWorker: $currentWorker,
            );

            if ($balance['has_missing_hourly_rates']) {
                throw new BusinessException(
                    'Worker cannot be fully paid because one or more attendances are missing an hourly rate.'
                );
            }

            $outstanding = (float) $balance['outstanding'];

            if ($outstanding <= 0) {
                throw new BusinessException(
                    'Worker has no outstanding salary.'
                );
            }

            return WorkerPayment::create([
                'company_id' => $currentWorker->company_id,
                'worker_id' => $worker->id,
                'amount' => $outstanding,
                'date' => now()->toDateString(),
                'note' => 'Full outstanding salary payment',
                'created_by' => $currentWorker->id,
            ]);
        });
    }
}
