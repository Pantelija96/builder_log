<?php

namespace App\Http\Controllers;

use App\DTO\SubcontractorLog\CreateSubcontractorLogData;
use App\DTO\SubcontractorLog\GetSubcontractorLogsData;
use App\DTO\SubcontractorLog\UpdateSubcontractorLogData;
use App\Http\Requests\SubcontractorLog\CreateSubcontractorLogRequest;
use App\Http\Requests\SubcontractorLog\DeleteSubcontractorLogRequest;
use App\Http\Requests\SubcontractorLog\GetSubcontractorLogsRequest;
use App\Http\Requests\SubcontractorLog\UpdateSubcontractorLogRequest;
use App\Http\Resources\SubcontractorLogResource;
use App\Models\DailyLog;
use App\Models\SubcontractorLog;
use App\Models\Worker;
use App\Services\SubcontractorLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcontractorLogController extends ApiController
{
    public function __construct(
        private readonly SubcontractorLogService $service,
    ) {
    }

    public function index(
        GetSubcontractorLogsRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            SubcontractorLogResource::collection(
                $this->service->get(
                    currentWorker: $worker,
                    data: GetSubcontractorLogsData::fromRequest($request),
                )
            )
        );
    }

    public function store(DailyLog $dailyLog, CreateSubcontractorLogRequest $request,): \Illuminate\Http\JsonResponse
    {

        /** @var Worker $worker */
        $worker = auth()->user();

        $subcontractorLog = $this->service->create(
            $dailyLog,
            CreateSubcontractorLogData::fromRequest($request),
            $worker,
        );

        return $this->success(
            SubcontractorLogResource::make(
                $subcontractorLog->load([
                    'subcontractor',
                    'creator',
                    'siteManager',
                ])
            ),
            'Subcontractor added successfully.'
        );
    }

    public function update(
        DailyLog $dailyLog,
        SubcontractorLog $subcontractorLog,
        UpdateSubcontractorLogRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $subcontractorLog = $this->service->update(
            subcontractorLog: $subcontractorLog,
            data: UpdateSubcontractorLogData::fromRequest($request),
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(SubcontractorLogResource::make($subcontractorLog), 'Subcontractor updated successfully.');
    }

    public function destroy(
        DailyLog $dailyLog,
        SubcontractorLog $subcontractorLog,
        DeleteSubcontractorLogRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->service->delete(
            subcontractorLog: $subcontractorLog,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'SubcontractorLog deleted successfully.'
        );
    }
}
