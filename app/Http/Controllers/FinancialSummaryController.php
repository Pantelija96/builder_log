<?php

namespace App\Http\Controllers;

use App\Http\Resources\FinancialSummaryResource;
use App\Services\FinancialSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialSummaryController extends ApiController
{
    public function __construct(
        private readonly FinancialSummaryService $service,
    ) {}

    public function index(): JsonResponse
    {
        return $this->success(
            FinancialSummaryResource::collection(
                $this->service->summaryForAllSiteManagers()
            )
        );
    }
}
