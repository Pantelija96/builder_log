<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetWorkersData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetWorkersRequest;
use App\Http\Resources\WorkerResource;
use App\Services\WorkerService;

class WorkerController extends ApiController
{
    public function __construct(
        private readonly WorkerService $workerService
    ) {
    }

    public function index(GetWorkersRequest $request)
    {
        $data = GetWorkersData::fromRequest($request);

        $workers = $this->workerService->getAll($data);

        return $this->success(
            data: WorkerResource::collection($workers),
        );
    }
}
