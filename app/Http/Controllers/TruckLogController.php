<?php

namespace App\Http\Controllers;

use App\DTO\TruckLog\CreateTruckLogData;
use App\DTO\TruckLog\CreateTruckLogForDriverData;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Http\Requests\TruckLog\CreateTruckLogForDriverRequest;
use App\Http\Requests\TruckLog\CreateTruckLogRequest;
use App\Http\Requests\TruckLog\DeleteTruckLogRequest;
use App\Http\Requests\TruckLog\UpdateTruckLogRequest;
use App\Http\Resources\MachineAssignmentResource;
use App\Http\Resources\MachineResource;
use App\Http\Resources\TruckLogResource;
use App\Models\DailyLog;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\TruckLogService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class TruckLogController extends ApiController
{
    public function __construct(
        private readonly TruckLogService $truckLogService,
    ) {}

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 409, description: 'The machine or assigned worker has an overlapping reservation.', type: "array{success: false, message: string, errors: null, error_code: 'MACHINE_TIME_CONFLICT'|'MACHINE_WORKER_TIME_CONFLICT'}")]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Invalid request or a site manager is required for attribution.', type: 'array{message: string, errors?: array<string, list<string>>}')]
    public function store(DailyLog $dailyLog, CreateTruckLogRequest $request): JsonResponse
    {
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

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 409, description: 'The machine or assigned worker has an overlapping reservation.', type: "array{success: false, message: string, errors: null, error_code: 'MACHINE_TIME_CONFLICT'|'MACHINE_WORKER_TIME_CONFLICT'}")]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function storeForDriver(CreateTruckLogForDriverRequest $request): JsonResponse
    {
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

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 409, description: 'The machine or assigned worker has an overlapping reservation.', type: "array{success: false, message: string, errors: null, error_code: 'MACHINE_TIME_CONFLICT'|'MACHINE_WORKER_TIME_CONFLICT'}")]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function update(TruckLog $truckLog, UpdateTruckLogRequest $request): JsonResponse
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

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function destroy(TruckLog $truckLog, DeleteTruckLogRequest $request): JsonResponse
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
