<?php

namespace App\Http\Controllers;

use App\DTO\WorkerAttendance\CreateWorkerAttendanceData;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\DTO\WorkerAttendance\UpdateWorkerAttendanceData;
use App\Http\Requests\WorkerAttendance\CreateWorkerAttendanceRequest;
use App\Http\Requests\WorkerAttendance\DeleteWorkerAttendanceRequest;
use App\Http\Requests\WorkerAttendance\GetAbsentWorkersRequest;
use App\Http\Requests\WorkerAttendance\GetGlobalWorkerAttendancesRequest;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendanceByWorkerRequest;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use App\Http\Requests\WorkerAttendance\UpdateWorkerAttendanceRequest;
use App\Http\Resources\WorkerAttendancePeriodResource;
use App\Http\Resources\WorkerAttendanceResource;
use App\Http\Resources\WorkerResource;
use App\Models\DailyLog;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Services\WorkerAttendanceService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class WorkerAttendanceController extends ApiController
{
    public function __construct(
        private readonly WorkerAttendanceService $workerAttendanceService,
    ) {}

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Invalid request or no manager attribution available for an administrator.', type: 'array{message: string, errors?: array<string, list<string>>}')]
    #[Response(status: 409, description: 'The worker already has an overlapping period.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function store(DailyLog $dailyLog, CreateWorkerAttendanceRequest $request): JsonResponse
    {

        /** @var Worker $worker */
        $worker = auth()->user();

        $attendance = $this->workerAttendanceService->create($dailyLog, CreateWorkerAttendanceData::fromRequest($request), $worker);

        return $this->success(
            WorkerAttendanceResource::make(
                $attendance
            ),
            'Worker attendance created successfully.'
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    public function index(DailyLog $dailyLog, GetWorkerAttendancesRequest $request): JsonResponse
    {
        return $this->success(
            WorkerAttendanceResource::collection(
                $this->workerAttendanceService->get($dailyLog, GetWorkerAttendancesData::fromRequest($request), $request->user())
            )
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    #[Response(status: 409, description: 'The worker already has an overlapping period.', type: 'array{message: string}')]
    public function update(DailyLog $dailyLog, WorkerAttendance $workerAttendance, UpdateWorkerAttendanceRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            WorkerAttendanceResource::make(
                $this->workerAttendanceService->update($dailyLog, $workerAttendance, UpdateWorkerAttendanceData::fromRequest($request), $worker)
            ),
            'Worker attendance updated successfully.'
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function destroy(DailyLog $dailyLog, WorkerAttendance $workerAttendance, DeleteWorkerAttendanceRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->workerAttendanceService->delete(dailyLog: $dailyLog, workerAttendance: $workerAttendance, currentWorker: $worker, reason: $request->string('reason')->toString());

        return $this->success(
            message: 'Worker attendance deleted successfully.'
        );
    }

    /**
     * List historical attendances or view presence at a point in time.
     *
     * For administrators, live=true checks server time in Europe/Belgrade, and at checks a supplied instant.
     * Point-in-time queries support construction_site_id. Do not combine live with at, or a point in time with date, date_from or date_to.
     * Without a point in time, date filters retain their historical meaning.
     */
    #[Response(status: 403, description: 'Only administrators can request point-in-time attendance.', type: 'array{message: string}')]
    public function getAll(GetGlobalWorkerAttendancesRequest $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            WorkerAttendanceResource::collection(
                $this->workerAttendanceService->getAll(
                    $worker,
                    GetWorkerAttendancesData::fromRequest($request),
                    $request->attendanceInstant(),
                )
            )
        );
    }

    /**
     * List attendance periods grouped by worker for a business date.
     *
     * Workers, operators and drivers in the caller's company appear even without a matching period.
     * The optional site and attributed manager filters apply to visible periods. An unmatched worker has an empty periods array.
     * Periods are ordered by start time, then by attendance ID.
     * An open period has a null finished_at. The creator is the person who recorded that particular entry.
     */
    #[Response(status: 200, description: 'Workers with their attendance periods for the date.', type: 'array{success: true, message: null, data: list<array{worker_id: int, worker: WorkerResource|null, periods: list<WorkerAttendancePeriodResource>}>}')]
    public function byWorker(GetWorkerAttendanceByWorkerRequest $request): JsonResponse
    {
        $actor = $request->user();
        $periodsByWorker = $this->workerAttendanceService->getForBusinessDate(
            $actor,
            $request->validated('date'),
            $request->integer('construction_site_id') ?: null,
            $request->integer('site_manager_id') ?: null,
        )->toBase()->groupBy('worker_id');

        $workers = $periodsByWorker->map(fn ($periods) => $periods->first()->worker)
            ->union($this->workerAttendanceService->getAttendanceRoster($actor)->keyBy('id'));

        return $this->success($workers
            ->map(fn (?Worker $worker, int $workerId) => [
                'worker_id' => $workerId,
                'worker' => $worker === null ? null : WorkerResource::make($worker),
                'periods' => WorkerAttendancePeriodResource::collection($periodsByWorker->get($workerId, [])),
            ])
            ->values());
    }

    public function absent(GetAbsentWorkersRequest $request): JsonResponse
    {
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
