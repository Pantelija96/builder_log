<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetConstructionSitesData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetConstructionSitesRequest;
use App\Http\Resources\ConstructionSiteResource;
use App\Models\Worker;
use App\Services\ConstructionSiteService;

class ConstructionSiteController extends ApiController
{
    public function __construct(
        protected readonly ConstructionSiteService $constructionSiteService,
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
}
