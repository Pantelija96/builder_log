<?php

namespace App\Http\Controllers;

use App\DTO\MachineAssignment\CreateMachineAssignmentData;
use App\DTO\MachineAssignment\CreateMachineAssignmentForOperatorData;
use App\DTO\MachineAssignment\GetMachineAssignmentsData;
use App\DTO\MachineAssignment\UpdateMachineAssignmentData;
use App\Http\Requests\Machine\CreateMachineAssignmentForOperatorRequest;
use App\Http\Requests\Machine\CreateMachineAssignmentRequest;
use App\Http\Requests\Machine\DeleteMachineAssignmentRequest;
use App\Http\Requests\Machine\GetMachineAssignmentsRequest;
use App\Http\Requests\Machine\UpdateMachineAssignmentRequest;
use App\Http\Resources\MachineAssignmentResource;
use App\Models\DailyLog;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Services\MachineAssignmentService;
use Illuminate\Http\JsonResponse;

class MachineAssignmentController extends ApiController
{
    public function __construct(
        private readonly MachineAssignmentService $machineAssignmentService,
    ) {
    }

    public function index(
        GetMachineAssignmentsRequest $request,
    ): JsonResponse {

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

    /**
     * Site Manager creates an assignment from DailyLog context.
     */
    public function store(
        DailyLog $dailyLog,
        CreateMachineAssignmentRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $assignment = $this->machineAssignmentService->create(
            dailyLog: $dailyLog,
            data: CreateMachineAssignmentData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            MachineAssignmentResource::make($assignment),
            'Machine assignment created successfully.',
        );
    }

    /**
     * Operator creates his own machine assignment.
     */
    public function storeForOperator(
        CreateMachineAssignmentForOperatorRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $assignment = $this->machineAssignmentService->createForOperator(
            data: CreateMachineAssignmentForOperatorData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            MachineAssignmentResource::make($assignment),
            'Machine assignment created successfully.',
        );
    }

    public function destroy(
        MachineAssignment $machineAssignment,
        DeleteMachineAssignmentRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $this->machineAssignmentService->delete(
            assignment: $machineAssignment,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Machine assignment deleted successfully.',
        );
    }

    public function update(
        MachineAssignment $machineAssignment,
        UpdateMachineAssignmentRequest $request,
    ): JsonResponse {

        /** @var Worker $worker */
        $worker = auth()->user();

        $assignment = $this->machineAssignmentService->update(
            assignment: $machineAssignment,
            data: UpdateMachineAssignmentData::fromRequest($request),
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            MachineAssignmentResource::make($assignment),
            'Machine assignment updated successfully.',
        );
    }
}
