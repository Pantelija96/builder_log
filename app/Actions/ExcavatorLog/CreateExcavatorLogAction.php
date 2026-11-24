<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\DTO\ExcavatorLog\CreateExcavatorLogData;
use App\DTO\ExcavatorLog\CreateExcavatorLogForOperatorData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\ExcavatorLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;
use App\Traits\ManagesMachineAssignments;
use Illuminate\Database\Eloquent\Builder;

class CreateExcavatorLogAction extends BaseAction
{
    use ManagesMachineAssignments;

    public function __construct(
        private readonly LoggingService $logging,
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
            $this->lockMachineAssignments($currentWorker);
            $dailyLog = $this->lockMachineDailyLog($dailyLog, $currentWorker);
            $this->ensureCanModify($dailyLog, $currentWorker);
            $this->ensureEditable($dailyLog);
            $this->ensureManagerAttribution($currentWorker);

            $machine = $this->findExcavator(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $worker = $this->findOperator(
                workerId: $data->workerId,
                companyId: $currentWorker->company_id,
            );

            $startedAt = $data->siteManagerStartedAt?->copy()->setTimezone(config('app.timezone'));
            $finishedAt = $data->siteManagerFinishedAt?->copy()->setTimezone(config('app.timezone'));
            MachinePeriod::validateManagerTimes($startedAt, $finishedAt);

            /*
             * Takeover je dozvoljen samo nad stvarnim
             * self-assignmentom operatora.
             *
             * Stari završeni manager assignment se nikada
             * ne koristi kao takeover za novi period.
             */
            $selfAssignment =
                $this->findSelfAssignmentForTakeover(
                    companyId: $currentWorker->company_id,
                    machineId: $machine->id,
                    workerId: $worker->id,
                    dailyLog: $dailyLog,
                    date: $dailyLog->date->toDateString(),
                    startedAt: $startedAt ?? now(),
                    finishedAt: $finishedAt,
                );

            if ($selfAssignment) {
                $excavatorLog =
                    $selfAssignment->excavatorLog;

                if (! $excavatorLog) {
                    throw new BusinessException(
                        __('Excavator assignment does not have an excavator log.')
                    );
                }

                /*
                 * Self-assignment koji preuzimamo izuzimamo,
                 * ali svi ostali assignmenti koriste isključivo
                 * Site Manager vremena za conflict proveru.
                 */
                MachinePeriod::ensureAvailable(
                    companyId: $currentWorker->company_id,
                    machineId: $machine->id,
                    workerId: $worker->id,
                    date: $dailyLog->date->toDateString(),
                    start: $startedAt,
                    finish: $finishedAt,
                    exceptAssignmentId: $selfAssignment->id,
                    machineMessage: __('Excavator is already in use during the selected period.'),
                    workerMessage: __('Operator is already assigned during the selected period.'),
                );

                $oldValues = $selfAssignment->getAttributes();
                $selfAssignment->update([
                    'daily_log_id' => $dailyLog->id,
                    'construction_site_id' => $dailyLog->construction_site_id,
                    'site_manager_id' => $currentWorker->id,
                ]);

                $excavatorLog->update([
                    'site_manager_started_at' => $startedAt,
                    'site_manager_finished_at' => $finishedAt,
                    'note_site_manager' => $data->noteSiteManager,
                ]);

                $this->logging->audit(
                    actor: $currentWorker,
                    subject: $selfAssignment,
                    event: LogEvent::MACHINE_ASSIGNMENT_TAKEN_OVER,
                    oldValues: $oldValues,
                    newValues: $selfAssignment->getAttributes(),
                );

                $this->syncMachineWorkerAttendanceAction->execute(
                    assignment: $selfAssignment->fresh('worker'),
                    startedAt: $startedAt,
                    finishedAt: $finishedAt,
                    advancePayment: $data->advancePayment,
                    actor: $currentWorker,
                );

                if ($currentWorker->isSiteManager()) {
                    $this->notifyOperator(
                        assignment: $selfAssignment,
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
             * Novi period.
             *
             * Završeni manager assignment istog dana ne blokira
             * novi period ako nema vremenskog preklapanja.
             *
             * Nepotvrđen assignment sa NULL manager vremenom
             * uvek blokira novu dodelu.
             */
            MachinePeriod::ensureAvailable(
                companyId: $currentWorker->company_id,
                machineId: $machine->id,
                workerId: $worker->id,
                date: $dailyLog->date->toDateString(),
                start: $startedAt,
                finish: $finishedAt,
                machineMessage: __('Excavator is already in use during the selected period.'),
                workerMessage: __('Operator is already assigned during the selected period.'),
            );

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $currentWorker->id,
                'machine_id' => $machine->id,
                'worker_id' => $worker->id,
                'date' => $dailyLog->date,
                'created_by' => $currentWorker->id,
            ]);

            $excavatorLog = ExcavatorLog::create([
                'machine_assignment_id' => $assignment->id,
                'worker_id' => $worker->id,
                'created_by' => $currentWorker->id,

                'site_manager_started_at' => $startedAt,
                'site_manager_finished_at' => $finishedAt,

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
                startedAt: $startedAt,
                finishedAt: $finishedAt,
                advancePayment: $data->advancePayment,
                actor: $currentWorker,
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

    public function executeForOperator(
        CreateExcavatorLogForOperatorData $data,
        Worker $currentWorker,
    ): ExcavatorLog {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {
            $this->lockMachineAssignments($currentWorker);
            if (! $currentWorker->isOperator()) {
                abort(403,
                    __('Only operators can create an excavator log this way.')
                );
            }

            $constructionSite =
                ConstructionSite::query()
                    ->where(
                        'company_id',
                        $currentWorker->company_id,
                    )
                    ->whereKey(
                        $data->constructionSiteId,
                    )
                    ->first();

            abort_unless($constructionSite, 404);

            $machine = $this->findExcavator(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $dailyLog = DailyLog::query()
                ->where(
                    'company_id',
                    $currentWorker->company_id,
                )
                ->where(
                    'construction_site_id',
                    $constructionSite->id,
                )
                ->whereDate(
                    'date',
                    today(),
                )
                ->lockForUpdate()
                ->first();

            if ($dailyLog) {
                $this->ensureEditable($dailyLog);
            }

            MachinePeriod::ensureAvailable(
                companyId: $currentWorker->company_id,
                machineId: $machine->id,
                workerId: $currentWorker->id,
                date: today()->toDateString(),
                start: null,
                finish: null,
                machineMessage: __('Excavator is already in use during the selected period.'),
                workerMessage: __('Operator is already assigned during the selected period.'),
            );

            $assignment = MachineAssignment::create([
                'company_id' => $currentWorker->company_id,
                'daily_log_id' => $dailyLog?->id,
                'construction_site_id' => $constructionSite->id,
                'site_manager_id' => null,
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

    private function findSelfAssignmentForTakeover(
        int $companyId,
        int $machineId,
        int $workerId,
        DailyLog $dailyLog,
        string $date,
        mixed $startedAt,
        mixed $finishedAt = null,
    ): ?MachineAssignment {
        $assignment = MachineAssignment::query()
            ->where(
                'company_id',
                $companyId,
            )
            ->where(
                'machine_id',
                $machineId,
            )
            ->where(
                'worker_id',
                $workerId,
            )
            ->where(
                'construction_site_id',
                $dailyLog->construction_site_id,
            )
            ->whereDate(
                'date',
                $date,
            )
            ->where(fn (Builder $query) => $query
                ->whereNull('daily_log_id')
                ->orWhere('daily_log_id', $dailyLog->id))
            ->whereColumn(
                'created_by',
                'worker_id',
            )
            ->whereHas(
                'excavatorLog',
                function (Builder $query) use (
                    $startedAt,
                    $finishedAt,
                ) {
                    $query
                        ->whereNull(
                            'site_manager_started_at'
                        )
                        ->whereNull(
                            'site_manager_finished_at'
                        )
                        ->where(
                            function (Builder $query) use (
                                $finishedAt,
                            ) {
                                $query
                                    ->whereNull(
                                        'operator_started_at'
                                    );

                                if ($finishedAt !== null) {
                                    $query->orWhere(
                                        'operator_started_at',
                                        '<',
                                        $finishedAt,
                                    );
                                } else {
                                    $query->orWhereNotNull(
                                        'operator_started_at'
                                    );
                                }
                            }
                        )
                        ->where(
                            function (Builder $query) use (
                                $startedAt,
                            ) {
                                $query
                                    ->whereNull(
                                        'operator_finished_at'
                                    )
                                    ->orWhere(
                                        'operator_finished_at',
                                        '>',
                                        $startedAt,
                                    );
                            }
                        );
                }
            )
            ->with('excavatorLog')
            ->latest('id')
            ->first();

        return $assignment?->isPendingSelfAssignment() ? $assignment : null;
    }

    private function notifyOperator(
        MachineAssignment $assignment,
        Worker $operator,
        Worker $assignedBy,
    ): void {
        $notification =
            $this->notifications->machineAssigned(
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
            ->where(
                'company_id',
                $companyId,
            )
            ->whereKey(
                $machineId,
            )
            ->first();

        abort_unless($machine, 404);

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
            ->where(
                'company_id',
                $companyId,
            )
            ->whereKey(
                $workerId,
            )
            ->first();

        abort_unless($worker, 404);

        if (! $worker->isOperator()) {
            throw new BusinessException(
                __('Selected worker is not an operator.')
            );
        }

        return $worker;
    }
}
