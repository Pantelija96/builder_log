<?php

namespace App\Http\Controllers;

use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use App\Http\Resources\WorkerSalaryOverviewResource;
use App\Http\Resources\WorkerSalaryResource;
use App\Http\Resources\WorkerSalarySummaryPresenter;
use App\Models\Worker;
use App\Services\WorkerSalaryService;
use Illuminate\Http\JsonResponse;

class WorkerSalaryController extends ApiController
{
    public function __construct(
        private readonly WorkerSalaryService $service,
    ) {
    }

    public function show(
        Worker $worker,
        GetWorkerAttendancesRequest $request,
    ) {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $data = GetWorkerAttendancesData::fromRequest(
            $request
        );

        $balance = $this->service->getBalance(
            worker: $worker,
            currentWorker: $currentWorker,
            data: $data,
        );

        return WorkerSalaryResource::make(
            $balance
        );
    }

    public function index(
        GetWorkerAttendancesRequest $request,
    ): JsonResponse {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $result = $this->service->getAllBalances(
            data: GetWorkerAttendancesData::fromRequest(
                $request
            ),
            currentWorker: $currentWorker,
        );

        return $this->success([
            'workers' => WorkerSalaryOverviewResource::collection(
                $result['workers']
            ),

            'summary' => WorkerSalarySummaryPresenter::present($result['summary']),
        ]);
    }
}
