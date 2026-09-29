<?php

namespace App\Http\Controllers;

use App\DTO\WorkerPayment\CreateWorkerPaymentData;
use App\DTO\WorkerPayment\GetWorkerPaymentsData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\WorkerPayment\CreateWorkerPaymentRequest;
use App\Http\Requests\WorkerPayment\GetWorkerPaymentsRequest;
use App\Http\Resources\WorkerPaymentResource;
use App\Models\Worker;
use App\Services\WorkerPaymentService;

class WorkerPaymentController extends ApiController
{
    public function __construct(
        private readonly WorkerPaymentService $service,
    ) {
    }

    public function index(GetWorkerPaymentsRequest $request)
    {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $payments = $this->service->get(
            data: GetWorkerPaymentsData::fromRequest($request),
            currentWorker: $currentWorker,
        );

        return WorkerPaymentResource::collection($payments);
    }

    public function store(CreateWorkerPaymentRequest $request)
    {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $payment = $this->service->create(
            data: CreateWorkerPaymentData::fromRequest($request),
            currentWorker: $currentWorker,
        );

        $payment->load([
            'worker',
            'creator',
        ]);

        return WorkerPaymentResource::make($payment);
    }

    public function show(
        int $workerPayment,
        GetWorkerPaymentsRequest $request,
    ) {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        return WorkerPaymentResource::make(
            $this->service->findById(
                id: $workerPayment,
                currentWorker: $currentWorker,
            )
        );
    }
}
