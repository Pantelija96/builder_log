<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetWorkersData;
use App\DTO\Worker\CreateWorkerData;
use App\DTO\Worker\GetWorkerWorkHistoryData;
use App\DTO\Worker\UpdateWorkerData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetWorkersRequest;
use App\Http\Requests\Worker\CreateWorkerRequest;
use App\Http\Requests\Worker\DeleteWorkerRequest;
use App\Http\Requests\Worker\GetWorkerWorkHistoryRequest;
use App\Http\Requests\Worker\UpdateWorkerRequest;
use App\Http\Resources\WorkerResource;
use App\Http\Resources\WorkerWorkHistoryResource;
use App\Models\Worker;
use App\Services\WorkerService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class WorkerController extends ApiController
{
    public function __construct(
        private readonly WorkerService $workerService
    ) {}

    public function index(GetWorkersRequest $request)
    {
        $data = GetWorkersData::fromRequest($request);
        /** @var Worker $actor */
        $actor = $request->user();
        $workers = $this->workerService->getAll($data, $actor);

        return $this->success(
            data: WorkerResource::collection($workers),
        );
    }

    #[Response(status: 404, description: 'The worker is missing or outside the company.', type: 'array{message: string}')]
    public function show(Worker $worker): JsonResponse
    {
        return $this->success(
            WorkerResource::make(
                $this->workerService->findById($worker->id, request()->user())
            )
        );
    }

    public function store(CreateWorkerRequest $request): JsonResponse
    {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $worker = $this->workerService->create(
            data: CreateWorkerData::fromRequest($request),
            currentWorker: $currentWorker,
        );

        return $this->success(
            WorkerResource::make($worker),
            'Worker created successfully.'
        );
    }

    public function update(Worker $worker, UpdateWorkerRequest $request): JsonResponse
    {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $worker = $this->workerService->update(
            worker: $worker,
            data: UpdateWorkerData::fromRequest($request),
            currentWorker: $currentWorker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            WorkerResource::make($worker),
            'Worker updated successfully.'
        );
    }

    public function destroy(Worker $worker, DeleteWorkerRequest $request): JsonResponse
    {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $this->workerService->delete(
            worker: $worker,
            currentWorker: $currentWorker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Worker deleted successfully.'
        );
    }

    #[Response(status: 404, description: 'The worker is missing or outside the company.', type: 'array{message: string}')]
    public function workHistory(Worker $worker, GetWorkerWorkHistoryRequest $request): JsonResponse
    {
        $data = GetWorkerWorkHistoryData::fromRequest(
            $request
        );

        return $this->success(
            WorkerWorkHistoryResource::make(
                $this->workerService->getWorkHistory(
                    worker: $worker,
                    data: $data,
                    currentWorker: $request->user(),
                )
            )
        );
    }
}
