<?php

namespace App\Services;

use App\Actions\MachineAssignment\GetCurrentMachineAssignmentAction;
use App\DTO\MachineAssignment\GetMachineAssignmentsData;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\QueryFilters\MachineAssignmentFilter;
use Illuminate\Database\Eloquent\Collection;

class MachineAssignmentService
{
    public function __construct(
        private readonly GetCurrentMachineAssignmentAction $getCurrentMachineAssignmentAction,
    ) {}

    /**
     * Get assignments for selected:
     */
    public function get(Worker $currentWorker, GetMachineAssignmentsData $data,): Collection {
        $query = MachineAssignment::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->with([
                'machine',
                'machine.owner',
                'machine.excavator',
                'machine.truck',

                'constructionSite',
                'siteManager',
                'worker',
                'creator',

                'excavatorLogWithTrashed',
                'truckLogWithTrashed',
            ]);

        return (new MachineAssignmentFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function getCurrentMachine(Worker $currentWorker, ?int $workerId = null,): ?MachineAssignment {
        return $this->getCurrentMachineAssignmentAction->execute(
            currentWorker: $currentWorker,
            workerId: $workerId,
        );
    }
}
