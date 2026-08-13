<?php

namespace App\Http\Controllers;

use App\DTO\MachineAssignment\GetMachineAssignmentsData;
use App\Http\Requests\MachineAssignment\GetMachineAssignmentsRequest;
use App\Http\Resources\MachineAssignmentResource;
use App\Models\Worker;
use App\Services\MachineAssignmentService;
use Illuminate\Http\JsonResponse;

class MachineAssignmentController extends ApiController
{
    public function __construct(
        private readonly MachineAssignmentService $machineAssignmentService,
    ) {}

    public function index(GetMachineAssignmentsRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            MachineAssignmentResource::collection(
                $this->machineAssignmentService->get(
                    currentWorker: $worker,
                    data: GetMachineAssignmentsData::fromRequest($request),
                )
            )
        );
    }

    public function current(): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $assignment = $this->machineAssignmentService->getCurrentMachine(
            currentWorker: $worker,
        );

        return $this->success(
            $assignment ? MachineAssignmentResource::make($assignment) : null,
            'No machine assigned to this operator'
        );
    }
}
