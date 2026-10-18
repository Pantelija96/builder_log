<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\DTO\ExcavatorLog\CreateExcavatorLogData;
use App\DTO\ExcavatorLog\CreateExcavatorLogForOperatorData;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;
use Illuminate\Database\Eloquent\Builder;

class CreateExcavatorLogAction extends BaseAction
{
    public function __construct(
        private readonly SyncMachineWorkerAttendanceAction $syncMachineWorkerAttendanceAction,
        private readonly NotificationService $notifications,
        private readonly WorkerPushNotificationService $pushNotifications,
    ) {}

    public function execute(
        DailyLog $dailyLog,
        CreateExcavatorLogData $data,
        Worker $currentWorker,
    ): ExcavatorLog {
        return $this->transaction(function () use (
            $dailyLog,
            $data,
            $currentWorker,
        ) {
            $this->ensureDailyLogAccess(
                dailyLog: $dailyLog,
                currentWorker: $currentWorker,
            );

            $machine = $this->findExcavator(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $worker = $this->findOperator(
                workerId: $data->workerId,
                companyId: $currentWorker->company_id,
            );

            /*
             * First check whether this excavator already has an assignment
             * for the selected date.
             *
             * An operator may have already taken the machine himself.
             * In that case the Site Manager takes over the existing
             * assignment instead of receiving "machine occupied".
             */
            $existingAssignment = MachineAssignment::query()
                ->where('company_id', $currentWorker->company_id)
                ->where('machine_id', $machine->id)
                ->whereDate('date', $dailyLog->date)
                ->with('excavatorLog')
                ->first();

            if ($existingAssignment) {
                /*
                 * Machine belongs to another operator.
                 */
                if ($existingAssignment->worker_id !== $worker->id) {
                    throw new BusinessException(
                        __('Excavator is already assigned to another operator.')
                    );
                }

                /*
                 * Same operator, but another construction site.
                 */
                if (
                    $existingAssignment->construction_site_id !==
                    $dailyLog->construction_site_id
                ) {
                    throw new BusinessException(
                        __('Excavator is already assigned to another construction site.')
                    );
                }

                /*
                 * Same machine + same operator + same site + same date.
                 *
                 * Treat this as takeover of the operator's existing
                 * self-assignment.
                 */
                $excavatorLog = $existingAssignment->excavatorLog;

                if (! $excavatorLog) {
                    throw new BusinessException(
                        __('Excavator assignment does not have an excavator log.')
                    );
                }

                $existingAssignment->update([
                    'daily_log_id' => $dailyLog->id,
                    'construction_site_id' => $dailyLog->construction_site_id,
                    'site_manager_id' => $dailyLog->site_manager_id,
                ]);

                $excavatorLog->update([
                    'site_manager_started_at' => $data->siteManagerStartedAt,
                    'site_manager_finished_at' => $data->siteManagerFinishedAt,
                    'note_site_manager' => $data->noteSiteManager,
                ]);

                $this->syncMachineWorkerAttendanceAction->execute(
                    assignment: $existingAssignment->fresh('worker'),
                    startedAt: $data->siteManagerStartedAt,
                    finishedAt: $data->siteManagerFinishedAt,
                    advancePayment: $data->advancePayment,
                );

                if ($currentWorker->isSiteManager()) {
                    $this->notifyOperator(
                        assignment: $existingAssignment,
                        operator: $worker,
                        assignedBy: $currentWorker,
                    );
                }

                return $excavatorLog->fresh([
                    'machineAssignment',
                    'worker',
                    'creator',
                ]);
            }

            /*
             * No assignment for this excavator on this date.
             */
            $this->ensureExcavatorIsAvailable(
                machineId: $machine->id,
                startedAt: $data->siteManagerStartedAt ?? now(),
                date: $dailyLog->date->toDateString(),
            );

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $dailyLog->site_manager_id,
                'machine_id' => $machine->id,
                'worker_id' => $worker->id,
                'date' => $dailyLog->date,
                'created_by' => $currentWorker->id,
            ]);

            $excavatorLog = ExcavatorLog::create([
                'machine_assignment_id' => $assignment->id,
                'worker_id' => $worker->id,
                'created_by' => $currentWorker->id,

                'site_manager_started_at' => $data->siteManagerStartedAt,
                'site_manager_finished_at' => $data->siteManagerFinishedAt,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'work_hours' => 0,
                'start_work_hours' => null,
                'finish_work_hours' => null,

                'fuel_added' => 0,
                'fuel_remaining' => null,

                'note_site_manager' => $data->noteSiteManager,
                'note_operator' => null,
            ]);

            $this->syncMachineWorkerAttendanceAction->execute(
                assignment: $assignment->fresh('worker'),
                startedAt: $data->siteManagerStartedAt,
                finishedAt: $data->siteManagerFinishedAt,
                advancePayment: $data->advancePayment,
            );

            if ($currentWorker->isSiteManager()) {
                $this->notifyOperator(
                    assignment: $assignment,
                    operator: $worker,
                    assignedBy: $currentWorker,
                );
            }

            return $excavatorLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }

    /**
     * Operator creates his own ExcavatorLog.
     */
    public function executeForOperator(
        CreateExcavatorLogForOperatorData $data,
        Worker $currentWorker,
    ): ExcavatorLog {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {
            if (! $currentWorker->isOperator()) {
                throw new BusinessException(
                    __('Only operators can create an excavator log this way.')
                );
            }

            $constructionSite = ConstructionSite::query()
                ->where('company_id', $currentWorker->company_id)
                ->whereKey($data->constructionSiteId)
                ->first();

            if (! $constructionSite) {
                throw new BusinessException(
                    __('Construction site not found.')
                );
            }

            $machine = $this->findExcavator(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $this->ensureExcavatorIsAvailable(
                machineId: $machine->id,
                startedAt: now(),
                date: today()->toDateString(),
            );

            $dailyLog = DailyLog::query()
                ->where('company_id', $currentWorker->company_id)
                ->where('construction_site_id', $constructionSite->id)
                ->whereDate('date', today())
                ->first();

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,
                'daily_log_id' => $dailyLog?->id,
                'construction_site_id' => $constructionSite->id,
                'site_manager_id' => $dailyLog?->site_manager_id,
                'machine_id' => $machine->id,
                'worker_id' => $currentWorker->id,
                'date' => today(),
                'created_by' => $currentWorker->id,
            ]);

            $excavatorLog = ExcavatorLog::create([
                'machine_assignment_id' => $assignment->id,
                'worker_id' => $currentWorker->id,
                'created_by' => $currentWorker->id,

                'site_manager_started_at' => null,
                'site_manager_finished_at' => null,

                'operator_started_at' => $data->operatorStartedAt,
                'operator_finished_at' => $data->operatorFinishedAt,

                'work_hours' => 0,
                'start_work_hours' => null,
                'finish_work_hours' => null,

                'fuel_added' => 0,
                'fuel_remaining' => null,

                'note_site_manager' => null,
                'note_operator' => $data->noteOperator,
            ]);

            return $excavatorLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }

    private function notifyOperator(
        MachineAssignment $assignment,
        Worker $operator,
        Worker $assignedBy,
    ): void {
        $notification = $this->notifications->machineAssigned(
            worker: $operator,
            assignment: $assignment,
            assignedBy: $assignedBy,
            machineType: 'excavator',
        );

        $this->pushNotifications->send(
            worker: $operator,
            title: $notification->title,
            body: $notification->message,
            data: [
                'type' => 'excavator_assigned',
                'machine_assignment_id' => $assignment->id,
                'machine_id' => $assignment->machine_id,
                'construction_site_id' => $assignment->construction_site_id,
                'notification_id' => $notification->id,
            ],
        );
    }

    private function findExcavator(
        int $machineId,
        int $companyId,
    ): Machine {
        $machine = Machine::query()
            ->where('company_id', $companyId)
            ->whereKey($machineId)
            ->first();

        if (! $machine) {
            throw new BusinessException(
                __('Machine not found.')
            );
        }

        if (! $machine->isExcavator()) {
            throw new BusinessException(
                __('Selected machine is not an excavator.')
            );
        }

        if (! $machine->isActive()) {
            throw new BusinessException(
                __('Excavator is not active.')
            );
        }

        return $machine;
    }

    private function findOperator(
        int $workerId,
        int $companyId,
    ): Worker {
        $worker = Worker::query()
            ->where('company_id', $companyId)
            ->whereKey($workerId)
            ->first();

        if (! $worker) {
            throw new BusinessException(
                __('Worker not found.')
            );
        }

        if (! $worker->isOperator()) {
            throw new BusinessException(
                __('Selected worker is not an operator.')
            );
        }

        return $worker;
    }

    private function ensureExcavatorIsAvailable(
        int $machineId,
        mixed $startedAt,
        ?string $date = null,
    ): void {
        $hasOverlappingLog = ExcavatorLog::query()
            ->whereHas(
                'machineAssignment',
                function (Builder $query) use (
                    $machineId,
                    $date,
                ) {
                    $query->where('machine_id', $machineId);

                    if ($date !== null) {
                        $query->whereDate('date', $date);
                    }
                }
            )
            ->where(function (Builder $query) use ($startedAt) {
                $query
                    ->whereNull('site_manager_started_at')
                    ->orWhere(
                        'site_manager_started_at',
                        '<=',
                        $startedAt
                    );
            })
            ->where(function (Builder $query) use ($startedAt) {
                $query
                    ->whereNull('site_manager_finished_at')
                    ->orWhere(
                        'site_manager_finished_at',
                        '>',
                        $startedAt
                    );
            })
            ->exists();

        if ($hasOverlappingLog) {
            throw new BusinessException(
                __('Excavator is currently in use.')
            );
        }
    }

    private function ensureDailyLogAccess(
        DailyLog $dailyLog,
        Worker $currentWorker,
    ): void {
        if ($dailyLog->company_id !== $currentWorker->company_id) {
            throw new BusinessException(
                __('Daily log not found.')
            );
        }

        if ($currentWorker->isAdmin()) {
            return;
        }

        if (
            ! $currentWorker->isSiteManager()
            || $dailyLog->site_manager_id !== $currentWorker->id
        ) {
            throw new BusinessException(
                __('You cannot manage this daily log.')
            );
        }
    }
}
