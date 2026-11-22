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
use Dedoc\Scramble\Attributes\Response;
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

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 422, description: 'Invalid request or no manager attribution available for an administrator.', type: 'array{message: string, errors?: array<string, list<string>>}')]
    #[Response(status: 500, description: 'The daily log is locked or already contains this entry.', type: 'array{message: string}')]
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
                    'constructionSite',
                ])
            ),
            'Subcontractor added successfully.'
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function update(
        DailyLog $dailyLog,
        SubcontractorLog $subcontractorLog,
        UpdateSubcontractorLogRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $subcontractorLog = $this->service->update(
            dailyLog: $dailyLog,
            subcontractorLog: $subcontractorLog,
            data: UpdateSubcontractorLogData::fromRequest($request),
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            SubcontractorLogResource::make(
                $subcontractorLog->load([
                    'subcontractor',
                    'creator',
                    'siteManager',
                    'constructionSite',
                ])
            ),
            'Subcontractor updated successfully.'
        );
    }

    #[Response(status: 403, description: 'The caller is not authorized for this daily log or attachment owner.', type: 'array{message: string}')]
    #[Response(status: 500, description: 'The daily log is locked.', type: 'array{message: string}')]
    public function destroy(
        DailyLog $dailyLog,
        SubcontractorLog $subcontractorLog,
        DeleteSubcontractorLogRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->service->delete(
            dailyLog: $dailyLog,
            subcontractorLog: $subcontractorLog,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'SubcontractorLog deleted successfully.'
        );
    }
}
