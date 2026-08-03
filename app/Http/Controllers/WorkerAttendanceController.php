<?php

namespace App\Http\Controllers;

use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Http\Requests\WorkerAttendance\CreateWorkerAttendanceRequest;
use App\Http\Requests\WorkerAttendance\DeleteWorkerAttendanceRequest;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use App\Http\Requests\WorkerAttendance\UpdateWorkerAttendanceRequest;
use App\Http\Resources\WorkerAttendanceResource;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\WorkerAttendanceService;
use Illuminate\Http\JsonResponse;

class WorkerAttendanceController extends ApiController
{
    public function __construct(
        private readonly WorkerAttendanceService $workerAttendanceService,
    ) {
    }

    public function store(DailyLog $dailyLog, CreateWorkerAttendanceRequest $request,): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $attendance = $this->workerAttendanceService->create($dailyLog, CreateWorkerAttendanceData::fromRequest($request), $worker,);

        return $this->success(
            WorkerAttendanceResource::make(
                $attendance->load([
                    'worker',
                    'creator',
                ])
            ),
            'Worker attendance created successfully.'
        );
    }

    public function index(DailyLog $dailyLog, GetWorkerAttendancesRequest $request,): JsonResponse {
        return $this->success(
            WorkerAttendanceResource::collection(
                $this->workerAttendanceService->get($dailyLog, GetWorkerAttendancesData::fromRequest($request),)
            )
        );
    }

    public function update(DailyLog $dailyLog, WorkerAttendance $workerAttendance, UpdateWorkerAttendanceRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            WorkerAttendanceResource::make(
                $this->workerAttendanceService->update($dailyLog, $workerAttendance, UpdateWorkerAttendanceData::fromRequest($request), $worker,)
            ),
            'Worker attendance updated successfully.'
        );
    }

    public function destroy(DailyLog $dailyLog, WorkerAttendance $workerAttendance, DeleteWorkerAttendanceRequest $request): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->workerAttendanceService->delete(dailyLog: $dailyLog, workerAttendance: $workerAttendance, currentWorker: $worker, reason: $request->string('reason')->toString(),);

        return $this->success(
            message: 'Worker attendance deleted successfully.'
        );
    }
}
