<?php

namespace App\Http\Controllers;

use App\DTO\DailyLog\CreateDailyLogData;
use App\DTO\DailyLog\GetDailyLogsData;
use App\Http\Requests\DailyLog\CreateDailyLogRequest;
use App\Http\Requests\DailyLog\GetDailyLogsRequest;
use App\Http\Requests\DailyLog\UnlockDailyLogRequest;
use App\Http\Resources\DailyLogResource;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Services\DailyLogService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DailyLogController extends ApiController
{
    public function __construct(
        private readonly DailyLogService $dailyLogService,
    ) {
    }

    public function index(GetDailyLogsRequest $request,): JsonResponse {
        $dailyLogs = $this->dailyLogService->get(
            GetDailyLogsData::fromRequest($request)
        );

        return $this->success(
            DailyLogResource::collection($dailyLogs)
        );
    }

    public function show(DailyLog $dailyLog,): JsonResponse {
        return $this->success(
            new DailyLogResource(
                $this->dailyLogService->findById($dailyLog->id)
            )
        );
    }

    public function store(CreateDailyLogRequest $request): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $dailyLog = $this->dailyLogService->create(worker: $worker, data: CreateDailyLogData::fromRequest($request));

        return $this->success(
            new DailyLogResource($dailyLog),
            'Daily log created successfully.',
            Response::HTTP_CREATED
        );
    }

    public function lock(DailyLog $dailyLog,): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $dailyLog = $this->dailyLogService
            ->lock($dailyLog, $worker);

        return $this->success(
            new DailyLogResource($dailyLog),
            'Daily log locked successfully.'
        );
    }

    public function unlock(UnlockDailyLogRequest $request,DailyLog $dailyLog,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $dailyLog = $this->dailyLogService->unlock(
            dailyLog: $dailyLog,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            new DailyLogResource($dailyLog),
            'Daily log unlocked successfully.',
        );
    }
}
