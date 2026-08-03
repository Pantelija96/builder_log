<?php

namespace App\Http\Controllers\Api;

use App\DTO\Requests\GetMachinesData;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Get\GetMachinesRequest;
use App\Http\Resources\MachineResource;
use App\Services\MachineService;

class MachineController extends ApiController
{
    public function __construct(
        protected readonly MachineService $machineService,
    ) {
    }

    public function index(GetMachinesRequest $request)
    {
        $data = GetMachinesData::fromRequest($request);

        $machines = $this->machineService->getAll($data);

        return $this->success(
            data: MachineResource::collection($machines),
        );
    }
}
