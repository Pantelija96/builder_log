<?php

namespace App\Http\Controllers;

use App\DTO\TruckLog\CreateTruckLogData;
use App\DTO\TruckLog\CreateTruckLogForDriverData;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Http\Requests\TruckLog\CreateTruckLogForDriverRequest;
use App\Http\Requests\TruckLog\CreateTruckLogRequest;
use App\Http\Requests\TruckLog\DeleteTruckLogRequest;
use App\Http\Requests\TruckLog\UpdateTruckLogRequest;
use App\Http\Resources\MachineResource;
use App\Http\Resources\TruckLogResource;
use App\Models\DailyLog;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\TruckLogService;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\MachineAssignmentResource;

class TruckLogController extends ApiController
{
    public function __construct(
        private readonly TruckLogService $truckLogService,
    ) {
    }

    public function store(DailyLog $dailyLog, CreateTruckLogRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $truckLog = $this->truckLogService->create(
            dailyLog: $dailyLog,
            data: CreateTruckLogData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            TruckLogResource::make($truckLog),
            'Truck assigned successfully.'
        );
    }

    public function storeForDriver(CreateTruckLogForDriverRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $truckLog = $this->truckLogService->createForDriver(
            data: CreateTruckLogForDriverData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            TruckLogResource::make($truckLog),
            'Truck assigned successfully.'
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

    public function available(): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $trucks = $this->truckLogService->getAvailable(
            currentWorker: $worker,
        );

        return $this->success(
            MachineResource::collection($trucks),
            'Available trucks retrieved successfully.',
        );
    }

    public function occupied(): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            MachineAssignmentResource::collection(
                $this->truckLogService->getOccupied(
                    currentWorker: $worker,
                )
            ),
            'Occupied trucks retrieved successfully.',
        );
    }
}
