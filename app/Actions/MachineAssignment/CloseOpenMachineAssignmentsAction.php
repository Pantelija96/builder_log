<?php

namespace App\Actions\MachineAssignment;

use App\Models\DailyLog;
use App\Models\MachineAssignment;
use Carbon\CarbonInterface;

class CloseOpenMachineAssignmentsAction
{
    public function execute(CarbonInterface $date): void
    {
        $assignments = MachineAssignment::query()
            ->whereDate('date', $date)
            ->with([
                'excavatorLog',
                'machine.excavator',
            ])
            ->get();

        $defaultWorkHours = (float) env('DEFAULT_MACHINE_WORK_HOURS', 9);

        $endOfDay = $date->copy()->endOfDay();

        foreach ($assignments as $assignment) {
            $log = $assignment->excavatorLog;

            if (! $log) {
                continue;
            }

            /*
             * Close missing Site Manager finish time.
             */
            if ($log->site_manager_finished_at === null) {
                $log->site_manager_finished_at = $endOfDay;
            }

            /*
             * Close missing Operator finish time.
             */
            if ($log->operator_finished_at === null) {
                $log->operator_finished_at = $endOfDay;
            }

            /*
             * Calculate machine work hours from machine-hour readings.
             */
            $startWorkHours = $log->start_work_hours;
            $finishWorkHours = $log->finish_work_hours;

            if ($startWorkHours !== null && $finishWorkHours !== null)
            {
                $workHours = (float) $finishWorkHours - (float) $startWorkHours;

                $log->work_hours = $workHours;

                /*
                 * Final machine-hour reading is the source of truth.
                 */
                $assignment->machine
                    ->excavator
                    ->update([
                        'total_work_hours' => $finishWorkHours,
                    ]);
            }
            elseif ($startWorkHours !== null) {
                /*
                 * We know where the machine started, but there is no final reading.
                 */
                $log->work_hours = $defaultWorkHours;

                $assignment->machine
                    ->excavator
                    ->increment('total_work_hours', $defaultWorkHours);
            }
            else {
                /*
                 * No machine-hour readings at all.
                 */
                $log->work_hours = $defaultWorkHours;

                $assignment->machine
                    ->excavator
                    ->increment('total_work_hours', $defaultWorkHours);
            }

            $log->save();
        }
    }
}
