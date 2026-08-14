<?php

namespace App\Actions\MachineAssignment;

use App\Models\MachineAssignment;
use Carbon\CarbonInterface;

class CloseOpenMachineAssignmentsAction
{
    public function execute(
        CarbonInterface $date,
    ): void {
        $assignments = MachineAssignment::query()
            ->whereDate('date', $date)
            ->with('excavatorLog')
            ->get();

        $defaultWorkHours = (float) env('DEFAULT_MACHINE_WORK_HOURS', 9);

        $endOfDay = $date
            ->copy()
            ->endOfDay();

        foreach ($assignments as $assignment) {
            $log = $assignment->excavatorLog;

            if (! $log) {
                continue;
            }

            $startedAt = $log->site_manager_started_at;
            $finishedAt = $log->site_manager_finished_at;

            /*
             * ----------------------------------------------------------
             * Site Manager is the source of truth.
             * ----------------------------------------------------------
             */

            if ($startedAt === null && $finishedAt === null) {
                $log->update([
                    'site_manager_finished_at' => $endOfDay,
                    'work_hours' => $defaultWorkHours,
                ]);

                continue;
            }

            if ($startedAt !== null && $finishedAt === null) {
                $finishedAt = $endOfDay;
                $log->update([
                    'site_manager_finished_at' => $finishedAt,
                    'work_hours' => $startedAt->diffInMinutes($finishedAt) / 60,
                ]);

                continue;
            }

            if ($startedAt !== null && $finishedAt !== null) {
                $log->update([
                    'work_hours' => $startedAt->diffInMinutes($finishedAt) / 60,
                ]);

                continue;
            }
        }
    }
}
