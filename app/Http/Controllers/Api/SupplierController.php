<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetSuppliersData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetSuppliersRequest;
use App\Http\Resources\SupplierResource;
use App\Services\SupplierService;

class SupplierController extends ApiController
{
    public function __construct(
        protected readonly SupplierService $supplierService,
    ) {
    }

    public function index(GetSuppliersRequest $request)
    {
        $data = GetSuppliersData::fromRequest($request);

        $suppliers = $this->supplierService->getAll($data);

        return $this->success(
            data: SupplierResource::collection($suppliers),
        );
    }
}
