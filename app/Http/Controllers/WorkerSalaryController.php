<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkerSalaryResource;
use App\Models\Worker;
use App\Services\WorkerSalaryService;
use Illuminate\Http\Request;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\Http\Requests\WorkerAttendance\GetWorkerAttendancesRequest;
use App\Http\Resources\WorkerSalaryOverviewResource;
use Illuminate\Http\JsonResponse;

class WorkerSalaryController extends ApiController
{
    public function __construct(
        private readonly WorkerSalaryService $service,
    ) {
    }

    public function show(Worker $worker, Request $request,) {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $balance = $this->service->getBalance(
            worker: $worker,
            currentWorker: $currentWorker,
        );

        return WorkerSalaryResource::make($balance);
    }

    public function index(GetWorkerAttendancesRequest $request,): JsonResponse {
        /** @var Worker $currentWorker */
        $currentWorker = $request->user();

        $result = $this->service->getAllBalances(
            data: GetWorkerAttendancesData::fromRequest($request),
            currentWorker: $currentWorker,
        );

        return $this->success([
            'workers' => WorkerSalaryOverviewResource::collection(
                $result['workers']
            ),

            'summary' => [
                'period' => [
                    'earned' => number_format(
                        $result['summary']['period']['earned'],
                        2,
                        '.',
                        ''
                    ),

                    'advances' => number_format(
                        $result['summary']['period']['advances'],
                        2,
                        '.',
                        ''
                    ),

                    'worked_hours' => number_format(
                        $result['summary']['period']['worked_hours'],
                        2,
                        '.',
                        ''
                    ),
                ],

                'total' => [
                    'earned' => number_format(
                        $result['summary']['total']['earned'],
                        2,
                        '.',
                        ''
                    ),

                    'advances' => number_format(
                        $result['summary']['total']['advances'],
                        2,
                        '.',
                        ''
                    ),

                    'payments' => number_format(
                        $result['summary']['total']['payments'],
                        2,
                        '.',
                        ''
                    ),

                    'outstanding' => number_format(
                        $result['summary']['total']['outstanding'],
                        2,
                        '.',
                        ''
                    ),
                ],
            ],
        ]);
    }
}
