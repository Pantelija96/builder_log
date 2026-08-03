<?php

namespace App\Services;

use App\DTO\Requests\GetMachinesData;
use App\Models\Machine;
use App\QueryFilters\MachineFilter;
use Illuminate\Database\Eloquent\Collection;

class MachineService
{
    public function getAll(GetMachinesData $data): Collection
    {
        $query = Machine::query()
            ->with([
                'company',
                'owner',
            ]);

        $query = (new MachineFilter($data))
            ->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }
}
