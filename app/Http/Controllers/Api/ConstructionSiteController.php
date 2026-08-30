<?php

namespace App\Http\Controllers\Api;

use App\DTO\ConstructionSite\GetConstructionSiteFinancialSummaryData;
use App\DTO\Requests\GetConstructionSitesData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\ConstructionSite\GetConstructionSiteFinancialSummaryRequest;
use App\Http\Requests\ConstructionSite\GetConstructionSiteStatisticsRequest;
use App\Http\Requests\Get\GetConstructionSitesRequest;
use App\Http\Resources\ConstructionSiteResource;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\WorkerAdvanceResource;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\Services\ConstructionSiteFinancialSummaryService;
use App\Services\ConstructionSiteService;
use App\Services\ConstructionSiteStatisticsService;
use Illuminate\Http\JsonResponse;

class ConstructionSiteController extends ApiController
{
    public function __construct(
        protected readonly ConstructionSiteService $constructionSiteService,
        private readonly ConstructionSiteFinancialSummaryService $financialSummaryService,
        private readonly ConstructionSiteStatisticsService $statisticsService,
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

    public function statistics(
        ConstructionSite $constructionSite,
        GetConstructionSiteStatisticsRequest $request,
    ): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        if (
            $worker->company_id !== $constructionSite->company_id
        ) {
            abort(404);
        }

        return $this->success(
            $this->statisticsService->get(
                constructionSite: $constructionSite,
                dateFrom: $request->date('date_from')->toDateString(),
                dateTo: $request->date('date_to')->toDateString(),
            )
        );
    }
}
