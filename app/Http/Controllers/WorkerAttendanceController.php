<?php

namespace App\Http\Controllers;

use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Http\Requests\WorkerAttendance\CreateWorkerAttendanceRequest;
use App\Http\Requests\WorkerAttendance\DeleteWorkerAttendanceRequest;
use App\Http\Requests\WorkerAttendance\GetAbsentWorkersRequest;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use App\Http\Requests\WorkerAttendance\UpdateWorkerAttendanceRequest;
use App\Http\Resources\WorkerAttendanceResource;
use App\Http\Resources\WorkerResource;
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

    public function getAll(GetWorkerAttendancesRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            WorkerAttendanceResource::collection(
                $this->workerAttendanceService->getAll(
                    $worker,
                    GetWorkerAttendancesData::fromRequest($request),
                )
            )
        );
    }

    public function absent(GetAbsentWorkersRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $result = $this->workerAttendanceService->getAbsentWorkers(
            currentWorker: $worker,

            date: $request->filled('date')
                ? $request->date('date')->toDateString()
                : null,

            dateFrom: $request->filled('date_from')
                ? $request->date('date_from')->toDateString()
                : null,

            dateTo: $request->filled('date_to')
                ? $request->date('date_to')->toDateString()
                : null,
        );

        return $this->success(
            collect($result)->map(
                fn (array $item) => [
                    'date' => $item['date'],

                    'absent_workers' => WorkerResource::collection(
                        $item['absent_workers']
                    ),
                ]
            )->values()
        );
    }
}
