<?php

namespace App\Http\Controllers;

use App\DTO\TruckLog\CreateTruckLogData;
use App\DTO\TruckLog\GetTruckLogsData;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Http\Requests\TruckLog\CreateTruckLogRequest;
use App\Http\Requests\TruckLog\DeleteTruckLogRequest;
use App\Http\Requests\TruckLog\GetTruckLogsRequest;
use App\Http\Requests\TruckLog\UpdateTruckLogRequest;
use App\Http\Resources\TruckLogResource;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\TruckLogService;
use Illuminate\Http\JsonResponse;

class TruckLogController extends ApiController
{
    public function __construct(
        private readonly TruckLogService $truckLogService,
    ) {
    }

    public function index(GetTruckLogsRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            TruckLogResource::collection(
                $this->truckLogService->get(
                    currentWorker: $worker,
                    data: GetTruckLogsData::fromRequest($request),
                )
            )
        );
    }

    public function show(TruckLog $truckLog,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $truckLog = $this->truckLogService->findById(
            currentWorker: $worker,
            id: $truckLog->id,
        );

        if (! $truckLog) {
            return $this->error(
                message: 'Truck log not found.',
                status: 404,
            );
        }

        return $this->success(
            TruckLogResource::make($truckLog)
        );
    }

    public function store(CreateTruckLogRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $truckLog = $this->truckLogService->create(
            data: CreateTruckLogData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            TruckLogResource::make($truckLog),
            'Truck log created successfully.',
        );
    }

    public function update(TruckLog $truckLog, UpdateTruckLogRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $truckLog = $this->truckLogService->update(
            truckLog: $truckLog,
            data: UpdateTruckLogData::fromRequest($request),
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            TruckLogResource::make($truckLog),
            'Truck log updated successfully.',
        );
    }

    public function destroy(TruckLog $truckLog, DeleteTruckLogRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->truckLogService->delete(
            truckLog: $truckLog,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Truck log deleted successfully.',
        );
    }
}
