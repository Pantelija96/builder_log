<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\Models\MachineAssignment;
use App\Models\WorkerAttendance;

class SyncMachineWorkerAttendanceAction extends BaseAction
{
    public function execute(MachineAssignment $assignment, mixed $startedAt, mixed $finishedAt, ?float $advancePayment = null,): ?WorkerAttendance {
        if ($startedAt === null || $assignment->daily_log_id === null || $assignment->site_manager_id === null) {
            return null;
        }

        $worker = $assignment->worker;

        if (! $worker->isOperator() && ! $worker->isDriver()) {
            return null;
        }

        $attendance = WorkerAttendance::query()
            ->where('machine_assignment_id', $assignment->id,)
            ->first();

        if (! $attendance) {
            return WorkerAttendance::create([
                'company_id' => $assignment->company_id,
                'daily_log_id' => $assignment->daily_log_id,
                'machine_assignment_id' => $assignment->id,
                'construction_site_id' => $assignment->construction_site_id,
                'site_manager_id' => $assignment->site_manager_id,
                'worker_id' => $assignment->worker_id,
                'date' => $assignment->date,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
                'advance_payment' => $advancePayment ?? 0,
                'hourly_rate' => $worker->hourly_rate,
                'created_by' => $assignment->site_manager_id,
            ]);
        }

        $values = [
            'daily_log_id' => $assignment->daily_log_id,
            'construction_site_id' => $assignment->construction_site_id,
            'site_manager_id' => $assignment->site_manager_id,
            'worker_id' => $assignment->worker_id,
            'date' => $assignment->date,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
        ];

        /*
         * Ako advance_payment nije poslat u request-u,
         * ne diramo postojeću akontaciju.
         */
        if ($advancePayment !== null) {
            $values['advance_payment'] = $advancePayment;
        }

        $attendance->update($values);

        return $attendance->refresh();
    }
}
