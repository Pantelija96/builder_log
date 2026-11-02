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

            $startedAt =
                $data->siteManagerStartedAt ?? now();

            $finishedAt =
                $data->siteManagerFinishedAt;

            /*
             * Takeover je dozvoljen samo nad stvarnim
             * self-assignmentom operatora.
             *
             * Stari završeni manager assignment se nikada
             * ne koristi kao takeover za novi period.
             */
            $selfAssignment =
                $this->findSelfAssignmentForTakeover(
                    companyId:
                    $currentWorker->company_id,
                    machineId:
                    $machine->id,
                    workerId:
                    $worker->id,
                    constructionSiteId:
                    $dailyLog->construction_site_id,
                    date:
                    $dailyLog->date->toDateString(),
                    startedAt:
                    $startedAt,
                    finishedAt:
                    $finishedAt,
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
                $this->ensureExcavatorIsAvailable(
                    machineId:
                    $machine->id,
                    startedAt:
                    $startedAt,
                    finishedAt:
                    $finishedAt,
                    date:
                    $dailyLog->date->toDateString(),
                    exceptAssignmentId:
                    $selfAssignment->id,
                );

                $this->ensureOperatorIsAvailable(
                    workerId:
                    $worker->id,
                    startedAt:
                    $startedAt,
                    finishedAt:
                    $finishedAt,
                    date:
                    $dailyLog->date->toDateString(),
                    exceptAssignmentId:
                    $selfAssignment->id,
                );

                $selfAssignment->update([
                    'daily_log_id' =>
                        $dailyLog->id,
                    'construction_site_id' =>
                        $dailyLog->construction_site_id,
                    'site_manager_id' =>
                        $dailyLog->site_manager_id,
                ]);

                $excavatorLog->update([
                    'site_manager_started_at' =>
                        $data->siteManagerStartedAt,
                    'site_manager_finished_at' =>
                        $data->siteManagerFinishedAt,
                    'note_site_manager' =>
                        $data->noteSiteManager,
                ]);

                $this->syncMachineWorkerAttendanceAction->execute(
                    assignment:
                    $selfAssignment->fresh('worker'),
                    startedAt:
                    $data->siteManagerStartedAt,
                    finishedAt:
                    $data->siteManagerFinishedAt,
                    advancePayment:
                    $data->advancePayment,
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
            $this->ensureExcavatorIsAvailable(
                machineId:
                $machine->id,
                startedAt:
                $startedAt,
                finishedAt:
                $finishedAt,
                date:
                $dailyLog->date->toDateString(),
            );

            $this->ensureOperatorIsAvailable(
                workerId:
                $worker->id,
                startedAt:
                $startedAt,
                finishedAt:
                $finishedAt,
                date:
                $dailyLog->date->toDateString(),
            );

            $assignment = MachineAssignment::create([
                'company_id' =>
                    $currentWorker->company_id,
                'daily_log_id' =>
                    $dailyLog->id,
                'construction_site_id' =>
                    $dailyLog->construction_site_id,
                'site_manager_id' =>
                    $dailyLog->site_manager_id,
                'machine_id' =>
                    $machine->id,
                'worker_id' =>
                    $worker->id,
                'date' =>
                    $dailyLog->date,
                'created_by' =>
                    $currentWorker->id,
            ]);

            $excavatorLog = ExcavatorLog::create([
                'machine_assignment_id' =>
                    $assignment->id,
                'worker_id' =>
                    $worker->id,
                'created_by' =>
                    $currentWorker->id,

                'site_manager_started_at' =>
                    $data->siteManagerStartedAt,
                'site_manager_finished_at' =>
                    $data->siteManagerFinishedAt,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'work_hours' => 0,
                'start_work_hours' => null,
                'finish_work_hours' => null,

                'fuel_added' => 0,
                'fuel_remaining' => null,

                'note_site_manager' =>
                    $data->noteSiteManager,
                'note_operator' => null,
            ]);

            $this->syncMachineWorkerAttendanceAction->execute(
                assignment:
                $assignment->fresh('worker'),
                startedAt:
                $data->siteManagerStartedAt,
                finishedAt:
                $data->siteManagerFinishedAt,
                advancePayment:
                $data->advancePayment,
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
            if (! $currentWorker->isOperator()) {
                throw new BusinessException(
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

            if (! $constructionSite) {
                throw new BusinessException(
                    __('Construction site not found.')
                );
            }

            $machine = $this->findExcavator(
                machineId:
                $data->machineId,
                companyId:
                $currentWorker->company_id,
            );

            /*
             * Operator može pokrenuti self-assignment.
             *
             * Postojeći assignment sa nepotpunim Site Manager
             * vremenima se smatra zauzetim bez obzira na
             * operator_finished_at.
             */
            $startedAt =
                $data->operatorStartedAt ?? now();

            $finishedAt =
                $data->operatorFinishedAt;

            $this->ensureExcavatorIsAvailable(
                machineId:
                $machine->id,
                startedAt:
                $startedAt,
                finishedAt:
                $finishedAt,
                date:
                today()->toDateString(),
            );

            $this->ensureOperatorIsAvailable(
                workerId:
                $currentWorker->id,
                startedAt:
                $startedAt,
                finishedAt:
                $finishedAt,
                date:
                today()->toDateString(),
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
                ->first();

            $assignment = MachineAssignment::create([
                'company_id' =>
                    $currentWorker->company_id,
                'daily_log_id' =>
                    $dailyLog?->id,
                'construction_site_id' =>
                    $constructionSite->id,
                'site_manager_id' =>
                    $dailyLog?->site_manager_id,
                'machine_id' =>
                    $machine->id,
                'worker_id' =>
                    $currentWorker->id,
                'date' =>
                    today(),
                'created_by' =>
                    $currentWorker->id,
            ]);

            $excavatorLog = ExcavatorLog::create([
                'machine_assignment_id' =>
                    $assignment->id,
                'worker_id' =>
                    $currentWorker->id,
                'created_by' =>
                    $currentWorker->id,

                'site_manager_started_at' => null,
                'site_manager_finished_at' => null,

                'operator_started_at' =>
                    $data->operatorStartedAt,
                'operator_finished_at' =>
                    $data->operatorFinishedAt,

                'work_hours' => 0,
                'start_work_hours' => null,
                'finish_work_hours' => null,

                'fuel_added' => 0,
                'fuel_remaining' => null,

                'note_site_manager' => null,
                'note_operator' =>
                    $data->noteOperator,
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
        int $constructionSiteId,
        string $date,
        mixed $startedAt,
        mixed $finishedAt = null,
    ): ?MachineAssignment {
        return MachineAssignment::query()
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
                $constructionSiteId,
            )
            ->whereDate(
                'date',
                $date,
            )
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
                'type' =>
                    'excavator_assigned',
                'machine_assignment_id' =>
                    $assignment->id,
                'machine_id' =>
                    $assignment->machine_id,
                'construction_site_id' =>
                    $assignment->construction_site_id,
                'notification_id' =>
                    $notification->id,
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
            ->where(
                'company_id',
                $companyId,
            )
            ->whereKey(
                $workerId,
            )
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
        mixed $finishedAt = null,
        ?string $date = null,
        ?int $exceptAssignmentId = null,
    ): void {
        $query = ExcavatorLog::query()
            ->whereHas(
                'machineAssignment',
                function (Builder $query) use (
                    $machineId,
                    $date,
                    $exceptAssignmentId,
                ) {
                    $query->where(
                        'machine_id',
                        $machineId,
                    );

                    if ($date !== null) {
                        $query->whereDate(
                            'date',
                            $date,
                        );
                    }

                    if ($exceptAssignmentId !== null) {
                        $query->where(
                            'id',
                            '!=',
                            $exceptAssignmentId,
                        );
                    }
                }
            );

        $this->applyManagerConflictCondition(
            query: $query,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
        );

        if ($query->exists()) {
            throw new BusinessException(
                __('Excavator is already in use during the selected period.')
            );
        }
    }

    private function ensureOperatorIsAvailable(
        int $workerId,
        mixed $startedAt,
        mixed $finishedAt = null,
        ?string $date = null,
        ?int $exceptAssignmentId = null,
    ): void {
        $query = ExcavatorLog::query()
            ->where(
                'worker_id',
                $workerId,
            )
            ->whereHas(
                'machineAssignment',
                function (Builder $query) use (
                    $date,
                    $exceptAssignmentId,
                ) {
                    if ($date !== null) {
                        $query->whereDate(
                            'date',
                            $date,
                        );
                    }

                    if ($exceptAssignmentId !== null) {
                        $query->where(
                            'id',
                            '!=',
                            $exceptAssignmentId,
                        );
                    }
                }
            );

        $this->applyManagerConflictCondition(
            query: $query,
            startedAt: $startedAt,
            finishedAt: $finishedAt,
        );

        if ($query->exists()) {
            throw new BusinessException(
                __('Operator is already assigned during the selected period.')
            );
        }
    }

    private function applyManagerConflictCondition(
        Builder $query,
        mixed $startedAt,
        mixed $finishedAt,
    ): void {
        /*
         * Ako novi period nema poznat početak,
         * ne možemo dokazati da se ne preklapa.
         *
         * Tada svaki drugi assignment istog datuma
         * predstavlja konflikt.
         */
        if ($startedAt === null) {
            return;
        }

        $query->where(
            function (Builder $query) use (
                $startedAt,
                $finishedAt,
            ) {
                /*
                 * Nepotvrđen / nezavršen Site Manager period
                 * UVEK blokira novu dodelu.
                 */
                $query
                    ->whereNull(
                        'site_manager_started_at'
                    )
                    ->orWhereNull(
                        'site_manager_finished_at'
                    )
                    ->orWhere(
                        function (Builder $query) use (
                            $startedAt,
                            $finishedAt,
                        ) {
                            /*
                             * Ovde su oba postojeća manager vremena
                             * poznata.
                             *
                             * Ako novi period ima završetak:
                             *
                             * existing_start < new_finish
                             * AND
                             * existing_finish > new_start
                             */
                            if ($finishedAt !== null) {
                                $query->where(
                                    'site_manager_started_at',
                                    '<',
                                    $finishedAt,
                                );
                            }

                            /*
                             * Ako novi period nema finish, posmatramo
                             * ga kao otvoren period od startedAt nadalje.
                             */
                            $query->where(
                                'site_manager_finished_at',
                                '>',
                                $startedAt,
                            );
                        }
                    );
            }
        );
    }

    private function ensureDailyLogAccess(
        DailyLog $dailyLog,
        Worker $currentWorker,
    ): void {
        if (
            $dailyLog->company_id !==
            $currentWorker->company_id
        ) {
            throw new BusinessException(
                __('Daily log not found.')
            );
        }

        if ($currentWorker->isAdmin()) {
            return;
        }

        if (
            ! $currentWorker->isSiteManager()
            || $dailyLog->site_manager_id !==
            $currentWorker->id
        ) {
            throw new BusinessException(
                __('You cannot manage this daily log.')
            );
        }
    }
}
