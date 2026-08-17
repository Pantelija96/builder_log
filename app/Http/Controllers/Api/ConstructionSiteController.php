<?php

namespace App\Http\Controllers\Api;

use App\DTO\ConstructionSite\GetConstructionSiteFinancialSummaryData;
use App\DTO\Requests\GetConstructionSitesData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\ConstructionSite\GetConstructionSiteFinancialSummaryRequest;
use App\Http\Requests\Get\GetConstructionSitesRequest;
use App\Http\Resources\ConstructionSiteResource;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\WorkerAdvanceResource;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\Services\ConstructionSiteFinancialSummaryService;
use App\Services\ConstructionSiteService;
use Illuminate\Http\JsonResponse;

class ConstructionSiteController extends ApiController
{
    public function __construct(
        protected readonly ConstructionSiteService $constructionSiteService,
        private readonly ConstructionSiteFinancialSummaryService $financialSummaryService,
    ) {
    }

    public function index(GetConstructionSitesRequest $request)
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $constructionSites = $this->constructionSiteService->getAll(
            worker: $worker,
            data: GetConstructionSitesData::fromRequest($request),
        );

        return $this->success(ConstructionSiteResource::collection($constructionSites));
    }

    public function financialSummary(ConstructionSite $constructionSite, GetConstructionSiteFinancialSummaryRequest $request,): JsonResponse
    {

        $data = $this->financialSummaryService->get(
            constructionSite: $constructionSite,
            data: GetConstructionSiteFinancialSummaryData::fromRequest($request),
        );

        return $this->success([
            'expenses' => ExpenseResource::collection(
                $data['expenses']
            ),

            'cash_advances' => WorkerAdvanceResource::collection(
                $data['cash_advances']
            ),
        ]);
    }
}
