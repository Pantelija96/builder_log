<?php

namespace App\Services;

use App\Actions\WorkerPayment\CreateWorkerPaymentAction;
use App\Actions\WorkerPayment\PayWorkerOutstandingAction;
use App\DTO\WorkerPayment\CreateWorkerPaymentData;
use App\DTO\WorkerPayment\GetWorkerPaymentsData;
use App\Models\Worker;
use App\Models\WorkerPayment;
use App\QueryFilters\WorkerPaymentFilter;
use Illuminate\Database\Eloquent\Collection;

class WorkerPaymentService
{
    public function __construct(
        private readonly CreateWorkerPaymentAction $createAction,
        private readonly PayWorkerOutstandingAction $payWorkerOutstandingAction,
    ) {
    }

    public function get(GetWorkerPaymentsData $data, Worker $currentWorker,): Collection {
        $query = WorkerPayment::query()
            ->where('company_id', $currentWorker->company_id)
            ->with([
                'worker',
                'creator',
            ]);

        $query = (new WorkerPaymentFilter($data))->apply($query);

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function create(CreateWorkerPaymentData $data, Worker $currentWorker,): WorkerPayment {
        return $this->createAction->execute(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function findById(int $id, Worker $currentWorker,): WorkerPayment {
        return WorkerPayment::query()
            ->whereKey($id)
            ->where('company_id', $currentWorker->company_id)
            ->with([
                'worker',
                'creator',
            ])
            ->firstOrFail();
    }

    public function payOutstanding(Worker $worker, Worker $currentWorker,): WorkerPayment {
        return $this->payWorkerOutstandingAction->execute(
            worker: $worker,
            currentWorker: $currentWorker,
        );
    }
}
