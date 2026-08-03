<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetCompaniesData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetCompaniesRequest;
use App\Http\Resources\CompanyResource;
use App\Services\CompanyService;

class CompanyController extends ApiController
{
    public function __construct(
        protected readonly CompanyService $companyService,
    ) {
    }

    public function index(GetCompaniesRequest $request)
    {
        $data = GetCompaniesData::fromRequest($request);

        $companies = $this->companyService->getAll($data);

        return $this->success(
            data: CompanyResource::collection($companies),
        );
    }
}
