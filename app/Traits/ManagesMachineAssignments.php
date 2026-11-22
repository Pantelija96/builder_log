<?php

namespace App\Traits;

use App\Models\Company;
use App\Models\DailyLog;
use App\Models\MachineAssignment;
use App\Models\Worker;

trait ManagesMachineAssignments
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    protected function lockMachineAssignments(Worker $actor): void
    {
        // Serialize competing worker and machine claims, including when no assignment exists yet.
        Company::query()->whereKey($actor->company_id)->lockForUpdate()->firstOrFail();
    }

    protected function lockMachineDailyLog(DailyLog $dailyLog, Worker $actor): DailyLog
    {
        return DailyLog::query()
            ->where('company_id', $actor->company_id)
            ->whereKey($dailyLog->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    protected function editableMachineDailyLog(MachineAssignment $assignment, Worker $actor, bool $ownUpdate = false): ?DailyLog
    {
        abort_unless(MachineAssignment::query()->withinCompany($actor->company_id)->whereKey($assignment->id)->exists(), 404);

        $query = DailyLog::query()
            ->where('company_id', $assignment->company_id)
            ->where('construction_site_id', $assignment->construction_site_id)
            ->whereDate('date', $assignment->date->toDateString());

        if ($assignment->daily_log_id !== null) {
            $query->whereKey($assignment->daily_log_id);
        }

        $dailyLog = $query->lockForUpdate()->first();
        abort_if($assignment->daily_log_id !== null && $dailyLog === null, 404);

        if (! $ownUpdate) {
            if ($dailyLog) {
                $this->ensureCanModify($dailyLog, $actor);
            } else {
                abort_unless($actor->isAdmin(), 403);
            }
        }

        if ($dailyLog) {
            $this->ensureEditable($dailyLog);
        }

        return $dailyLog;
    }
}
