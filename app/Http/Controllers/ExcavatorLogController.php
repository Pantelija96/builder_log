<?php

namespace App\Http\Controllers;

use App\DTO\ExcavatorLog\CreateExcavatorLogData;
use App\DTO\ExcavatorLog\CreateExcavatorLogForOperatorData;
use App\DTO\ExcavatorLog\UpdateExcavatorLogData;
use App\Http\Requests\ExcavatorLog\CreateExcavatorLogForOperatorRequest;
use App\Http\Requests\ExcavatorLog\CreateExcavatorLogRequest;
use App\Http\Requests\ExcavatorLog\DeleteExcavatorLogRequest;
use App\Http\Requests\ExcavatorLog\UpdateExcavatorLogRequest;
use App\Http\Resources\ExcavatorLogResource;
use App\Http\Resources\MachineAssignmentResource;
use App\Http\Resources\MachineResource;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Worker;
use App\Services\ExcavatorLogService;
use Illuminate\Http\JsonResponse;

class ExcavatorLogController extends ApiController
{
    public function __construct(
        private readonly ExcavatorLogService $excavatorLogService,
    ) {}

    public function store(DailyLog $dailyLog, CreateExcavatorLogRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $excavatorLog = $this->excavatorLogService->create(
            dailyLog: $dailyLog,
            data: CreateExcavatorLogData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            ExcavatorLogResource::make($excavatorLog),
            'Excavator log created successfully.',
        );
    }

    public function storeForOperator(CreateExcavatorLogForOperatorRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $excavatorLog = $this->excavatorLogService->createForOperator(
            data: CreateExcavatorLogForOperatorData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            ExcavatorLogResource::make($excavatorLog),
            'Excavator log created successfully.',
        );
    }

    public function update(ExcavatorLog $excavatorLog, UpdateExcavatorLogRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $excavatorLog = $this->excavatorLogService->update(
            excavatorLog: $excavatorLog,
            data: UpdateExcavatorLogData::fromRequest($request),
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            ExcavatorLogResource::make($excavatorLog),
            'Excavator log updated successfully.',
        );
    }

    public function destroy(ExcavatorLog $excavatorLog, DeleteExcavatorLogRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->excavatorLogService->delete(
            excavatorLog: $excavatorLog,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Excavator log deleted successfully.',
        );
    }

    public function available(): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            MachineResource::collection(
                $this->excavatorLogService->getAvailable(
                    currentWorker: $worker,
                )
            ),
            'Available excavators retrieved successfully.',
        );
    }

    public function occupied(): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            MachineAssignmentResource::collection(
                $this->excavatorLogService->getOccupied(
                    currentWorker: $worker,
                )
            ),
            'Occupied excavators retrieved successfully.',
        );
    }
}
