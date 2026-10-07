<?php

namespace App\Http\Controllers\Api;

use App\DTO\ConstructionSite\CreateConstructionSiteData;
use App\DTO\ConstructionSite\GetConstructionSiteFinancialSummaryData;
use App\DTO\ConstructionSite\UpdateConstructionSiteData;
use App\DTO\Requests\GetConstructionSitesData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\ConstructionSite\CreateConstructionSiteRequest;
use App\Http\Requests\ConstructionSite\DeleteConstructionSiteRequest;
use App\Http\Requests\ConstructionSite\GetConstructionSiteFinancialSummaryRequest;
use App\Http\Requests\ConstructionSite\GetConstructionSiteStatisticsRequest;
use App\Http\Requests\ConstructionSite\UpdateConstructionSiteRequest;
use App\Http\Requests\Get\GetConstructionSitesRequest;
use App\Http\Resources\ConstructionSiteResource;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\WorkerAdvanceResource;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\Services\ConstructionSiteFinancialSummaryService;
use App\Services\ConstructionSiteService;
use App\Services\ConstructionSiteStatisticsService;
use Carbon\Carbon;
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

    public function statistics(ConstructionSite $constructionSite, GetConstructionSiteStatisticsRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = auth()->user();

        if ($worker->company_id !== $constructionSite->company_id) {
            abort(404);
        }

        return $this->success(
            $this->statisticsService->get(
                constructionSite: $constructionSite,
                dateFrom: Carbon::parse($request->validated('date_from')),
                dateTo: Carbon::parse($request->validated('date_to')),
            )
        );
    }

    public function store(CreateConstructionSiteRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $constructionSite = $this->constructionSiteService->create(
            data: CreateConstructionSiteData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            ConstructionSiteResource::make($constructionSite),
            'Construction site created successfully.'
        );
    }

    public function update(ConstructionSite $constructionSite, UpdateConstructionSiteRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $constructionSite = $this->constructionSiteService->update(
            constructionSite: $constructionSite,
            data: UpdateConstructionSiteData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            ConstructionSiteResource::make($constructionSite),
            'Construction site updated successfully.'
        );
    }

    public function destroy(ConstructionSite $constructionSite, DeleteConstructionSiteRequest $request,): JsonResponse {
        /** @var Worker $worker */
        $worker = $request->user();

        $this->constructionSiteService->delete(
            constructionSite: $constructionSite,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Construction site deleted successfully.'
        );
    }
}
