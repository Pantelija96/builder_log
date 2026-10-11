<?php

namespace App\Http\Controllers;

use App\DTO\Financial\GetConstructionFinancialSummaryData;
use App\Http\Requests\Financial\GetConstructionFinancialSummaryRequest;
use App\Models\Worker;
use App\Services\ConstructionFinancialSummaryService;
use Illuminate\Http\JsonResponse;

class ConstructionFinancialSummaryController extends ApiController
{
    public function __construct(
        private readonly ConstructionFinancialSummaryService $service,
    ) {
    }

    public function index(GetConstructionFinancialSummaryRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        return $this->success(
            $this->service->get(
                currentWorker: $worker,
                data: GetConstructionFinancialSummaryData::fromRequest(
                    $request
                ),
            )
        );
    }
}
