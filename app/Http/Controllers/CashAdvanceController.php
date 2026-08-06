<?php

namespace App\Http\Controllers;

use App\DTO\CashAdvance\CreateCashAdvanceData;
use App\DTO\CashAdvance\GetCashAdvancesData;
use App\DTO\CashAdvance\UpdateCashAdvanceData;
use App\Http\Requests\CashAdvance\CreateCashAdvanceRequest;
use App\Http\Requests\CashAdvance\DeleteCashAdvanceRequest;
use App\Http\Requests\CashAdvance\GetCashAdvancesRequest;
use App\Http\Requests\CashAdvance\UpdateCashAdvanceRequest;
use App\Http\Resources\CashAdvanceResource;
use App\Models\CashAdvance;
use App\Models\Worker;
use App\Services\CashAdvanceService;
use Illuminate\Http\JsonResponse;

class CashAdvanceController extends ApiController
{
    public function __construct(
        private readonly CashAdvanceService $cashAdvanceService,
    ) {
    }

    public function index(
        GetCashAdvancesRequest $request,
    ): JsonResponse {

        return $this->success(
            CashAdvanceResource::collection(
                $this->cashAdvanceService->get(
                    GetCashAdvancesData::fromRequest($request)
                )
            )
        );
    }

    public function store(
        CreateCashAdvanceRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $cashAdvance = $this->cashAdvanceService->create(
            data: CreateCashAdvanceData::fromRequest($request),
            worker: $worker,
        );

        return $this->success(
            CashAdvanceResource::make(
                $cashAdvance->load([
                    'siteManager',
                    'creator',
                ])
            ),
            'Cash advance created successfully.'
        );
    }

    public function update(
        CashAdvance $cashAdvance,
        UpdateCashAdvanceRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $cashAdvance = $this->cashAdvanceService->update(
            cashAdvance: $cashAdvance,
            data: UpdateCashAdvanceData::fromRequest($request),
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            CashAdvanceResource::make(
                $cashAdvance->load([
                    'siteManager',
                    'creator',
                ])
            ),
            'Cash advance updated successfully.'
        );
    }

    public function destroy(
        CashAdvance $cashAdvance,
        DeleteCashAdvanceRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->cashAdvanceService->delete(
            cashAdvance: $cashAdvance,
            worker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Cash advance deleted successfully.'
        );
    }
}
