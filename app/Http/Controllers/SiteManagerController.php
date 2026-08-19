<?php

namespace App\Http\Controllers;

use App\DTO\SiteManager\GetSiteManagerOverallData;
use App\Http\Requests\SiteManager\GetSiteManagerOverallRequest;
use App\Http\Resources\ConstructionSiteResource;
use App\Http\Resources\SiteManagerOverallResource;
use App\Models\Worker;
use App\Services\SiteManagerService;
use Illuminate\Http\JsonResponse;

class SiteManagerController extends ApiController
{
    public function __construct(
        private readonly SiteManagerService $siteManagerService,
    )
    {}

    public function overall(int $siteManager, GetSiteManagerOverallRequest $request,): JsonResponse
    {
        return $this->success(
            SiteManagerOverallResource::collection(
                $this->siteManagerService->getOverall(
                    siteManagerId: $siteManager,
                    data: GetSiteManagerOverallData::fromRequest($request),
                )
            )
        );
    }

    public function assignedSites(Worker $siteManager,): JsonResponse
    {
        if (! $siteManager->isSiteManager()) {
            abort(404);
        }

        return $this->success(
            ConstructionSiteResource::collection(
                $this->siteManagerService->getAssignedConstructionSites(
                    siteManager: $siteManager,
                )
            )
        );
    }
}
