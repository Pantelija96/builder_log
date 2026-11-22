<?php

namespace App\Http\Controllers;

use App\Http\Resources\WorkerResource;
use App\Models\ConstructionSite;
use App\Models\Worker;
use App\Services\ConstructionSiteAssignmentService;
use Dedoc\Scramble\Attributes\Response as ApiResponseDocumentation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ConstructionSiteAssignmentController extends ApiController
{
    public function __construct(
        private readonly ConstructionSiteAssignmentService $service,
    ) {}

    #[ApiResponseDocumentation(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    public function index(ConstructionSite $constructionSite): JsonResponse
    {
        $this->ensureCompany($constructionSite);
        $workers = $this->service->getAssigned(
            $constructionSite
        );

        return $this->success(WorkerResource::collection($workers));
    }

    #[ApiResponseDocumentation(status: 403, description: 'The caller lacks the required role or site assignment.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 422, description: 'The target or request does not meet validation requirements.', type: 'array{message: string}')]
    public function assign(ConstructionSite $constructionSite, Worker $worker): JsonResponse
    {
        $this->ensureCompany($constructionSite, $worker);
        abort_unless(request()->user()->isAdmin(), 403);
        abort_unless($worker->isSiteManager() && $worker->is_active, 422);
        $this->service->assign(
            $constructionSite,
            $worker,
        );

        return $this->success(
            message: 'Site manager assigned successfully.'
        );
    }

    #[ApiResponseDocumentation(status: 403, description: 'The caller lacks the required role or site assignment.', type: 'array{message: string}')]
    #[ApiResponseDocumentation(status: 404, description: 'The resource is missing or belongs to a different company.', type: 'array{message: string}')]
    public function remove(ConstructionSite $constructionSite, Worker $worker): Response
    {
        $this->ensureCompany($constructionSite, $worker);
        abort_unless(request()->user()->isAdmin(), 403);
        $this->service->remove(
            $constructionSite,
            $worker,
        );

        return $this->noContent();
    }

    private function ensureCompany(ConstructionSite $site, ?Worker $worker = null): void
    {
        $companyId = request()->user()->company_id;
        abort_unless($site->company_id === $companyId && ($worker === null || $worker->company_id === $companyId), 404);
    }
}
