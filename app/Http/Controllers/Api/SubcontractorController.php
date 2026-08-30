<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetSubcontractorsData;
use App\DTO\Subcontractor\CreateSubcontractorData;
use App\DTO\Subcontractor\UpdateSubcontractorData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetSubcontractorsRequest;
use App\Http\Requests\Subcontractor\CreateSubcontractorRequest;
use App\Http\Requests\Subcontractor\DeleteSubcontractorRequest;
use App\Http\Requests\Subcontractor\UpdateSubcontractorRequest;
use App\Http\Resources\SubcontractorResource;
use App\Models\Subcontractor;
use App\Models\Worker;
use App\Services\SubcontractorService;
use Illuminate\Http\JsonResponse;

class SubcontractorController extends ApiController
{
    public function __construct(
        private readonly SubcontractorService $subcontractorService,
    ) {
    }

    public function index(
        GetSubcontractorsRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            SubcontractorResource::collection(
                $this->subcontractorService->get(
                    currentWorker: $worker,
                    data: GetSubcontractorsData::fromRequest($request),
                )
            )
        );
    }

    public function show(
        Subcontractor $subcontractor,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $subcontractor = $this->subcontractorService->findById(
            currentWorker: $worker,
            id: $subcontractor->id,
        );

        if (! $subcontractor) {
            return $this->error(
                message: 'Subcontractor not found.',
                status: 404,
            );
        }

        return $this->success(
            SubcontractorResource::make($subcontractor),
        );
    }

    public function store(
        CreateSubcontractorRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $subcontractor = $this->subcontractorService->create(
            data: CreateSubcontractorData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            SubcontractorResource::make($subcontractor),
            'Subcontractor created successfully.',
        );
    }

    public function update(
        Subcontractor $subcontractor,
        UpdateSubcontractorRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $subcontractor = $this->subcontractorService->update(
            subcontractor: $subcontractor,
            data: UpdateSubcontractorData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            SubcontractorResource::make($subcontractor),
            'Subcontractor updated successfully.',
        );
    }

    public function destroy(
        Subcontractor $subcontractor,
        DeleteSubcontractorRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->subcontractorService->delete(
            subcontractor: $subcontractor,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Subcontractor deleted successfully.',
        );
    }
}
