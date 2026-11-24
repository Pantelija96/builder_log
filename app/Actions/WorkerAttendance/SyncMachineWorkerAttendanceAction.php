<?php

namespace App\Actions\WorkerAttendance;

use App\Actions\BaseAction;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Models\WorkerAttendance;

class SyncMachineWorkerAttendanceAction extends BaseAction
{
    public function execute(
        MachineAssignment $assignment,
        mixed $startedAt,
        mixed $finishedAt,
        ?float $advancePayment = null,
        ?Worker $actor = null,
    ): ?WorkerAttendance {
        if ($assignment->daily_log_id === null || $assignment->site_manager_id === null) {
            return null;
        }

        $worker = $assignment->worker;
        if (! $worker || (! $worker->isOperator() && ! $worker->isDriver())) {
            return null;
        }

        $attendance = $assignment->workerAttendance()->withTrashed()->first();

        if ($startedAt === null) {
            if ($attendance && ! $attendance->trashed()) {
                $attendance->delete();
            }

            return null;
        }

        $values = [
            'company_id' => $assignment->company_id,
            'daily_log_id' => $assignment->daily_log_id,
            'machine_assignment_id' => $assignment->id,
            'construction_site_id' => $assignment->construction_site_id,
            'worker_id' => $assignment->worker_id,
            'date' => $assignment->date,
            'started_at' => $startedAt,
            'finished_at' => $finishedAt,
            'hourly_rate' => $worker->hourly_rate,
        ];

        if (! $attendance) {
            return WorkerAttendance::create($values + [
                'site_manager_id' => $actor?->isSiteManager() ? $actor->id : $assignment->site_manager_id,
                'created_by' => $actor?->id ?? $assignment->site_manager_id,
                'advance_payment' => $advancePayment ?? 0,
            ]);
        }

        if ($attendance->trashed()) {
            $attendance->restore();
        }

        if ($advancePayment !== null) {
            $values['advance_payment'] = $advancePayment;
        }

        $attendance->update($values);

        return $attendance->refresh();
    }
}
