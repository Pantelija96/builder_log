<?php

namespace App\Actions\MachineAssignment;

use App\Models\MachineAssignment;
use Carbon\CarbonInterface;

class CloseOpenMachineAssignmentsAction
{
    public function execute(CarbonInterface $date,): void
    {
        $assignments = MachineAssignment::query()
            ->whereDate('date', $date)
            ->with('excavatorLog')
            ->get();

        $defaultWorkHours = (float) env('DEFAULT_MACHINE_WORK_HOURS', 9);

        $endOfDay = $date->copy()->endOfDay();

        foreach ($assignments as $assignment) {
            $log = $assignment->excavatorLog;

            if (! $log) {
                continue;
            }

            $siteManagerStartedAt = $log->site_manager_started_at;
            $siteManagerFinishedAt = $log->site_manager_finished_at;

            $operatorStartedAt = $log->operator_started_at;
            $operatorFinishedAt = $log->operator_finished_at;

            $siteManagerHasCompletePair = $siteManagerStartedAt !== null && $siteManagerFinishedAt !== null;
            $operatorHasCompletePair = $operatorStartedAt !== null && $operatorFinishedAt !== null;

            $sourceStartedAt = null;
            $sourceFinishedAt = null;

            if ($siteManagerHasCompletePair && $operatorHasCompletePair) {
                if ($siteManagerFinishedAt->greaterThanOrEqual($operatorFinishedAt)
                )
                {
                    $sourceStartedAt = $siteManagerStartedAt;
                    $sourceFinishedAt = $siteManagerFinishedAt;
                }
                else {
                    $sourceStartedAt = $operatorStartedAt;
                    $sourceFinishedAt = $operatorFinishedAt;
                }
            }
            elseif ($siteManagerHasCompletePair)
            {
                $sourceStartedAt = $siteManagerStartedAt;
                $sourceFinishedAt = $siteManagerFinishedAt;
            }
            elseif ($operatorHasCompletePair)
            {
                $sourceStartedAt = $operatorStartedAt;
                $sourceFinishedAt = $operatorFinishedAt;
            }


            $values = [];

            if ($siteManagerFinishedAt === null)
            {
                $values['site_manager_finished_at'] = $endOfDay;
            }

            if ($operatorFinishedAt === null)
            {
                $values['operator_finished_at'] = $endOfDay;
            }


            if ($sourceStartedAt !== null && $sourceFinishedAt !== null)
            {
                $values['work_hours'] = $sourceStartedAt->diffInMinutes($sourceFinishedAt) / 60;
            } else {
                $values['work_hours'] = $defaultWorkHours;
            }

            $log->update($values);
        }
    }
}
