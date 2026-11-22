<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetSuppliersData;
use App\DTO\Supplier\CreateSupplierData;
use App\DTO\Supplier\UpdateSupplierData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetSuppliersRequest;
use App\Http\Requests\Supplier\CreateSupplierRequest;
use App\Http\Requests\Supplier\DeleteSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\Worker;
use App\Services\SupplierService;
use Illuminate\Http\JsonResponse;

class SupplierController extends ApiController
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {
    }

    public function index(
        GetSuppliersRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        return $this->success(
            SupplierResource::collection(
                $this->supplierService->get(
                    currentWorker: $worker,
                    data: GetSuppliersData::fromRequest($request),
                )
            )
        );
    }

    public function show(
        Supplier $supplier,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        $supplier = $this->supplierService->findById(
            currentWorker: $worker,
            id: $supplier->id,
        );

        if (! $supplier) {
            return $this->error(
                message: 'Supplier not found.',
                status: 404,
            );
        }

        return $this->success(
            SupplierResource::make($supplier),
        );
    }

    public function store(
        CreateSupplierRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $supplier = $this->supplierService->create(
            data: CreateSupplierData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            SupplierResource::make($supplier),
            'Supplier created successfully.',
        );
    }

    public function update(
        Supplier $supplier,
        UpdateSupplierRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $supplier = $this->supplierService->update(
            supplier: $supplier,
            data: UpdateSupplierData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            SupplierResource::make($supplier),
            'Supplier updated successfully.',
        );
    }

    public function destroy(
        Supplier $supplier,
        DeleteSupplierRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $this->supplierService->delete(
            supplier: $supplier,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Supplier deleted successfully.',
        );
    }
}
