<?php

namespace App\Http\Controllers;

use App\DTO\SiteManager\GetSiteManagerOverallData;
use App\Http\Requests\SiteManager\GetSiteManagerOverallRequest;
use App\Http\Resources\ConstructionSiteResource;
use App\Http\Resources\SiteManagerOverallResource;
use App\Models\Worker;
use App\Services\SiteManagerService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

class SiteManagerController extends ApiController
{
    public function __construct(
        private readonly SiteManagerService $siteManagerService,
    ) {}

    #[Response(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    public function overall(int $siteManager, GetSiteManagerOverallRequest $request): JsonResponse
    {
        return $this->success(
            SiteManagerOverallResource::collection(
                $this->siteManagerService->getOverall(
                    siteManagerId: $siteManager,
                    data: GetSiteManagerOverallData::fromRequest($request),
                    worker: $request->user(),
                )
            )
        );
    }

    #[Response(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    public function assignedSites(Worker $siteManager): JsonResponse
    {
        if ($siteManager->company_id !== request()->user()->company_id || ! $siteManager->isSiteManager()) {
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
