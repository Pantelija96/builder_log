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
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ExcavatorLogController extends ApiController
{
    public function __construct(
        private readonly ExcavatorLogService $excavatorLogService,
    ) {}

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked or a machine or worker period conflicts.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Invalid request or a site manager is required for attribution.', type: 'array{message: string, errors?: array<string, list<string>>}')]
    public function store(DailyLog $dailyLog, CreateExcavatorLogRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        Log::info('ExcavatorLog received', ['dailyLog' => $dailyLog->toArray()]);
        Log::info('UpdateExcavatorLogRequest received', ['Request' => $request->toArray()]);

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

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked or a machine or worker period conflicts.', type: 'array{message: string}')]
    public function storeForOperator(CreateExcavatorLogForOperatorRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        Log::info('storeForOperator received', ['Request' => $request->toArray()]);

        $excavatorLog = $this->excavatorLogService->createForOperator(
            data: CreateExcavatorLogForOperatorData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            ExcavatorLogResource::make($excavatorLog),
            'Excavator log created successfully.',
        );
    }

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked or a machine or worker period conflicts.', type: 'array{message: string}')]
    public function update(ExcavatorLog $excavatorLog, UpdateExcavatorLogRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        Log::info('ExcavatorLog received', ['excavatorLog' => $excavatorLog->toArray()]);
        Log::info('UpdateExcavatorLogRequest received', ['Request' => $request->toArray()]);

        $excavatorLog = $this->excavatorLogService->update(
            excavatorLog: $excavatorLog,
            data: UpdateExcavatorLogData::fromRequest($request),
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(ExcavatorLogResource::make($excavatorLog), 'Excavator log updated successfully.');
    }

    #[Response(status: 403, description: 'The caller lacks the required role, ownership or daily log membership.', type: 'array{message: string}')]
    #[Response(status: 404, description: 'A referenced record is missing or outside the company or parent daily log.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function destroy(ExcavatorLog $excavatorLog, DeleteExcavatorLogRequest $request): JsonResponse
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
