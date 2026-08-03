<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetSubcontractorsData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetSubcontractorsRequest;
use App\Http\Resources\SubcontractorResource;
use App\Services\SubcontractorService;

class SubcontractorController extends ApiController
{
    public function __construct(
        protected readonly SubcontractorService $subcontractorService,
    ) {
    }

    public function index(GetSubcontractorsRequest $request)
    {
        $data = GetSubcontractorsData::fromRequest($request);

        $subcontractors = $this->subcontractorService->getAll($data);

        return $this->success(
            data: SubcontractorResource::collection($subcontractors),
        );
    }
}
