<?php

namespace App\Http\Controllers;

use App\DTO\Machine\CreateMachineData;
use App\DTO\Machine\GetMachinesData;
use App\DTO\Machine\UpdateMachineData;
use App\Http\Requests\Get\GetMachinesRequest;
use App\Http\Requests\Machine\CreateMachineRequest;
use App\Http\Requests\Machine\DeleteMachineRequest;
use App\Http\Requests\Machine\UpdateMachineRequest;
use App\Http\Resources\MachineResource;
use App\Models\Machine;
use App\Models\Worker;
use App\Services\MachineService;
use Illuminate\Http\JsonResponse;

class MachineController extends ApiController
{
    public function __construct(
        private readonly MachineService $machineService,
    ) {}

    public function index(GetMachinesRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        return $this->success(
            MachineResource::collection(
                $this->machineService->get(
                    currentWorker: $worker,
                    data: GetMachinesData::fromRequest($request),
                )
            )
        );
    }

    public function show(Machine $machine,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $machine = $this->machineService->findById(
            currentWorker: $worker,
            id: $machine->id,
        );

        if (! $machine) {
            return $this->error(
                message: 'Machine not found.',
                status: 404,
            );
        }

        return $this->success(
            MachineResource::make($machine)
        );
    }

    public function store(CreateMachineRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $machine = $this->machineService->create(
            data: CreateMachineData::fromRequest($request),
            currentWorker: $worker,
        );

        return $this->success(
            MachineResource::make($machine),
            'Machine created successfully.',
        );
    }

    public function update(Machine $machine, UpdateMachineRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $machine = $this->machineService->update(
            machine: $machine,
            data: UpdateMachineData::fromRequest($request),
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            MachineResource::make($machine),
            'Machine updated successfully.',
        );
    }

    public function destroy(Machine $machine, DeleteMachineRequest $request,): JsonResponse
    {
        /** @var Worker $worker */
        $worker = auth()->user();

        $this->machineService->delete(
            machine: $machine,
            currentWorker: $worker,
            reason: $request->string('reason')->toString(),
        );

        return $this->success(
            message: 'Machine deleted successfully.',
        );
    }

}
