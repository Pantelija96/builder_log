<?php

namespace App\Services;

use App\Actions\MachineAssignment\CreateMachineAssignmentAction;
use App\Actions\MachineAssignment\DeleteMachineAssignmentAction;
use App\Actions\MachineAssignment\UpdateMachineAssignmentAction;
use App\DTO\MachineAssignment\CreateMachineAssignmentData;
use App\DTO\MachineAssignment\CreateMachineAssignmentForOperatorData;
use App\DTO\MachineAssignment\GetMachineAssignmentsData;
use App\DTO\MachineAssignment\UpdateMachineAssignmentData;
use App\Models\DailyLog;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\QueryFilters\MachineAssignmentFilter;
use Illuminate\Database\Eloquent\Collection;

class MachineAssignmentService
{
    public function __construct(
        private readonly CreateMachineAssignmentAction $createMachineAssignmentAction,
        private readonly DeleteMachineAssignmentAction $deleteMachineAssignmentAction,
        private readonly UpdateMachineAssignmentAction $updateMachineAssignmentAction,
    ) {
    }

    /**
     * Get assignments for selected:
     *
     * - date
     * - construction site
     * - site manager
     */
    public function get(
        Worker $currentWorker,
        GetMachineAssignmentsData $data,
    ): Collection {

        $this->ensureCanAccessContext(
            currentWorker: $currentWorker,
            constructionSiteId: $data->constructionSiteId,
            siteManagerId: $data->siteManagerId,
        );

        $query = MachineAssignment::query()
            ->where(
                'company_id',
                $currentWorker->company_id,
            )
            ->with([
                'machine',
                'constructionSite',
                'siteManager',
                'worker',
                'creator',
            ]);

        return (new MachineAssignmentFilter($data))
            ->apply($query)
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    /**
     * Create assignment from a DailyLog context.
     */
    public function create(
        DailyLog $dailyLog,
        CreateMachineAssignmentData $data,
        Worker $currentWorker,
    ): MachineAssignment {

        $this->ensureCanManageDailyLog(
            dailyLog: $dailyLog,
            currentWorker: $currentWorker,
        );

        return $this->createMachineAssignmentAction->executeForDailyLog(
            dailyLog: $dailyLog,
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    /**
     * Create assignment when Operator selects a machine himself.
     */
    public function createForOperator(
        CreateMachineAssignmentForOperatorData $data,
        Worker $currentWorker,
    ): MachineAssignment {

        if (! $currentWorker->isOperator()) {
            abort(403);
        }

        return $this->createMachineAssignmentAction->executeForOperator(
            data: $data,
            currentWorker: $currentWorker,
        );
    }

    public function delete(
        MachineAssignment $assignment,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->ensureCompanyAccess(
            assignment: $assignment,
            currentWorker: $currentWorker,
        );

        $this->ensureCanManageAssignment(
            assignment: $assignment,
            currentWorker: $currentWorker,
        );

        $this->deleteMachineAssignmentAction->execute(
            assignment: $assignment,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    public function update(
        MachineAssignment $assignment,
        UpdateMachineAssignmentData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): MachineAssignment {

        $this->ensureCompanyAccess(
            assignment: $assignment,
            currentWorker: $currentWorker,
        );

        $this->ensureCanManageAssignment(
            assignment: $assignment,
            currentWorker: $currentWorker,
        );

        return $this->updateMachineAssignmentAction->execute(
            assignment: $assignment,
            data: $data,
            currentWorker: $currentWorker,
            reason: $reason,
        );
    }

    private function ensureCanManageDailyLog(
        DailyLog $dailyLog,
        Worker $currentWorker,
    ): void {

        if (
            $dailyLog->company_id !== $currentWorker->company_id
        ) {
            abort(404);
        }

        if ($currentWorker->isAdmin()) {
            return;
        }

        if (
            ! $currentWorker->isSiteManager()
            || $dailyLog->site_manager_id !== $currentWorker->id
        ) {
            abort(403);
        }
    }

    private function ensureCanManageAssignment(
        MachineAssignment $assignment,
        Worker $currentWorker,
    ): void {

        if ($currentWorker->isAdmin()) {
            return;
        }

        if (
            ! $currentWorker->isSiteManager()
            || $assignment->site_manager_id !== $currentWorker->id
        ) {
            abort(403);
        }
    }

    private function ensureCompanyAccess(
        MachineAssignment $assignment,
        Worker $currentWorker,
    ): void {

        if (
            $assignment->company_id !== $currentWorker->company_id
        ) {
            abort(404);
        }
    }

    private function ensureCanAccessContext(
        Worker $currentWorker,
        int $constructionSiteId,
        int $siteManagerId,
    ): void {

        if ($currentWorker->isAdmin()) {
            return;
        }

        if (
            ! $currentWorker->isSiteManager()
            || $siteManagerId !== $currentWorker->id
        ) {
            abort(403);
        }

        $hasAccessToSite = $currentWorker
            ->constructionSites()
            ->whereKey($constructionSiteId)
            ->exists();

        if (! $hasAccessToSite) {
            abort(403);
        }
    }
}
