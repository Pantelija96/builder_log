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
use Dedoc\Scramble\Attributes\Response as ApiResponseDocumentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DailyLogController extends ApiController
{
    public function __construct(
        private readonly DailyLogService $dailyLogService,
    ) {}

    public function index(GetDailyLogsRequest $request): JsonResponse
    {
        $dailyLogs = $this->dailyLogService->get(
            GetDailyLogsData::fromRequest($request),
            $request->user(),
        );

        return $this->success(
            DailyLogResource::collection($dailyLogs)
        );
    }

    public function show(Request $request, DailyLog $dailyLog): JsonResponse
    {
        return $this->success(
            new DailyLogResource(
                $this->dailyLogService->findById($dailyLog->id, $request->user())
            )
        );
    }

    #[ApiResponseDocumentation(status: 403, description: 'The caller lacks the required role or site assignment.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 409, description: 'A deleted daily log already occupies the site and date.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 500, description: 'The request did not meet the daily log business rules.', type: 'array{message: string}')]
    public function store(CreateDailyLogRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $dailyLog = $this->dailyLogService->create(worker: $worker, data: CreateDailyLogData::fromRequest($request));

        return $this->success(
            new DailyLogResource($dailyLog),
            'Daily log created successfully.',
            Response::HTTP_CREATED
        );
    }

    #[ApiResponseDocumentation(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 500, description: 'The request did not meet the daily log business rules.', type: 'array{message: string}')]
    public function lock(DailyLog $dailyLog): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $dailyLog = $this->dailyLogService
            ->lock($dailyLog, $worker);

        return $this->success(
            new DailyLogResource($dailyLog),
            'Daily log locked successfully.'
        );
    }

    #[ApiResponseDocumentation(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 500, description: 'The request did not meet the daily log business rules.', type: 'array{message: string}')]
    public function unlock(UnlockDailyLogRequest $request, DailyLog $dailyLog): JsonResponse
    {
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
