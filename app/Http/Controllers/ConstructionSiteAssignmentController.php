<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkerResource;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\Services\ConstructionSiteAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ConstructionSiteAssignmentController extends ApiController
{
    public function __construct(
        private readonly ConstructionSiteAssignmentService $service,
    ) {
    }

    public function index(ConstructionSite $constructionSite,): JsonResponse {
        $workers = $this->service->getAssigned(
            $constructionSite
        );

        return $this->success(WorkerResource::collection($workers));
    }

    public function assign(ConstructionSite $constructionSite, Worker $worker): JsonResponse {
        $this->service->assign(
            $constructionSite,
            $worker,
        );

        return $this->success(
            message: 'Site manager assigned successfully.'
        );
    }

    public function remove(ConstructionSite $constructionSite, Worker $worker): Response
    {
        $this->service->remove(
            $constructionSite,
            $worker,
        );

        return $this->noContent();
    }
}
