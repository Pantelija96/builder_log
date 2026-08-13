<?php

namespace App\Actions\MachineAssignment;

use App\Models\DailyLog;
use App\Models\MachineAssignment;
use Carbon\Carbon;

class CloseOpenMachineAssignmentsAction
{
    public function execute(
        DailyLog $dailyLog,
    ): void {

        $assignments = MachineAssignment::query()
            ->where('daily_log_id', $dailyLog->id)
            ->whereNull(['site_manager_finished_at', 'operator_finished_at'])
            ->get();

        $defaultWorkHours = (int) env('DEFAULT_MACHINE_WORK_HOURS', 9);

        $midnight = $dailyLog->date
            ->copy()
            ->addDay()
            ->startOfDay();

        foreach ($assignments as $assignment) {

            $finishedAt = $assignment->started_at
                ->copy()
                ->addHours($defaultWorkHours);

            if ($finishedAt->greaterThan($midnight)) {
                $finishedAt = $midnight->copy();
            }

            $assignment->update([
                'site_manager_finished_at' => $finishedAt,
                'operator_finished_at' => $finishedAt,
            ]);
        }
    }
}
