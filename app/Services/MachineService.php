<?php

namespace App\Services;

use App\Actions\Machine\CreateMachineAction;
use App\Actions\Machine\DeleteMachineAction;
use App\Actions\Machine\UpdateMachineAction;
use App\DTO\Machine\CreateMachineData;
use App\DTO\Machine\GetMachinesData;
use App\DTO\Machine\UpdateMachineData;
use App\Models\Machine;
use App\Models\Worker;
use App\QueryFilters\MachineFilter;
use Illuminate\Database\Eloquent\Collection;

class MachineService
{
    public function __construct(
        private readonly CreateMachineAction $createMachineAction,
        private readonly UpdateMachineAction $updateMachineAction,
        private readonly DeleteMachineAction $deleteMachineAction,
    ) {
    }

    private function query(Worker $currentWorker)
    {
        return Machine::query()
            ->where('company_id', $currentWorker->company_id)
            ->with([
                'owner',
                'excavator',
                'truck',
            ]);
    }

    public function getAll(GetMachinesData $data): Collection
    {
        $query = Machine::query()
            ->with([
                'company',
                'owner',
                'excavator',
                'truck',
            ]);

        $query = (new MachineFilter($data))
            ->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function get(
        Worker $currentWorker,
        GetMachinesData $data,
    ): Collection {
        return (new MachineFilter($data))
            ->apply(
                $this->query($currentWorker)
            )
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function findById(
        Worker $currentWorker,
        int $id,
    ): ?Machine {
        return $this->query($currentWorker)
            ->whereKey($id)
            ->first();
    }

    public function create(
        CreateMachineData $data,
        Worker $currentWorker,
    ): Machine {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        return $this->createMachineAction->execute(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function update(
        Machine $machine,
        UpdateMachineData $data,
        Worker $currentWorker,
    ): Machine {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        $this->ensureCompanyAccess(
            $machine,
            $currentWorker,
        );

        return $this->updateMachineAction->execute(
            machine: $machine,
            data: $data,
        );
    }

    public function delete(
        Machine $machine,
        Worker $currentWorker,
        string $reason,
    ): void {
        if (! $currentWorker->isAdmin()) {
            abort(403);
        }

        $this->ensureCompanyAccess(
            $machine,
            $currentWorker,
        );

        $this->deleteMachineAction->execute(
            machine: $machine,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    private function ensureCompanyAccess(
        Machine $machine,
        Worker $currentWorker,
    ): void {
        if ($machine->company_id !== $currentWorker->company_id) {
            abort(404);
        }
    }
}
