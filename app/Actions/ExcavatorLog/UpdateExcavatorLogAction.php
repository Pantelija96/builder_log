<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\DTO\ExcavatorLog\UpdateExcavatorLogData;
use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\ExcavatorLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class UpdateExcavatorLogAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly SyncMachineWorkerAttendanceAction $syncMachineWorkerAttendanceAction,
        private readonly NotificationService $notifications,
        private readonly WorkerPushNotificationService $pushNotifications,
    ) {}

    public function execute(
        ExcavatorLog $excavatorLog,
        UpdateExcavatorLogData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): ExcavatorLog {
        return $this->transaction(function () use (
            $excavatorLog,
            $data,
            $currentWorker,
            $reason,
        ) {
            $oldValues =
                $excavatorLog->getAttributes();

            $values = [];

            Log::info(
                'UpdateExcavatorLogAction received',
                [
                    'ExcavatorLog' =>
                        $excavatorLog->toArray(),
                ],
            );

            Log::info(
                'UpdateExcavatorLogData received',
                [
                    'UpdateExcavatorLogData' =>
                        $data,
                ],
            );

            Log::info(
                'Worker received',
                [
                    'Worker' =>
                        $currentWorker->toArray(),
                ],
            );

            if (in_array(
                'site_manager_started_at',
                $data->providedFields,
                true,
            )) {
                $values['site_manager_started_at'] =
                    $data->siteManagerStartedAt;
            }

            if (in_array(
                'site_manager_finished_at',
                $data->providedFields,
                true,
            )) {
                $values['site_manager_finished_at'] =
                    $data->siteManagerFinishedAt;
            }

            if (in_array(
                'operator_started_at',
                $data->providedFields,
                true,
            )) {
                $values['operator_started_at'] =
                    $data->operatorStartedAt;
            }

            if (in_array(
                'operator_finished_at',
                $data->providedFields,
                true,
            )) {
                $values['operator_finished_at'] =
                    $data->operatorFinishedAt;
            }

            if (in_array(
                'work_hours',
                $data->providedFields,
                true,
            )) {
                $values['work_hours'] =
                    $data->workHours;
            }

            if (in_array(
                'start_work_hours',
                $data->providedFields,
                true,
            )) {
                $values['start_work_hours'] =
                    $data->startWorkHours;
            }

            if (in_array(
                'finish_work_hours',
                $data->providedFields,
                true,
            )) {
                $values['finish_work_hours'] =
                    $data->finishWorkHours;
            }

            if (in_array(
                'fuel_added',
                $data->providedFields,
                true,
            )) {
                $values['fuel_added'] =
                    $data->fuelAdded;
            }

            if (in_array(
                'fuel_remaining',
                $data->providedFields,
                true,
            )) {
                $values['fuel_remaining'] =
                    $data->fuelRemaining;
            }

            if (in_array(
                'note_site_manager',
                $data->providedFields,
                true,
            )) {
                $values['note_site_manager'] =
                    $data->noteSiteManager;
            }

            if (in_array(
                'note_operator',
                $data->providedFields,
                true,
            )) {
                $values['note_operator'] =
                    $data->noteOperator;
            }

            $operatorStartedDay =
                $currentWorker->isOperator()
                && $data->started === true;

            $operatorFinishedDay =
                $currentWorker->isOperator()
                && $data->finished === true;

            $excavatorLog->load(
                'machineAssignment.worker'
            );

            $assignment =
                $excavatorLog->machineAssignment;

            if (! $assignment) {
                throw new BusinessException(
                    'Excavator log does not have a machine assignment.'
                );
            }

            /*
             * Samo Site Manager vremena menjaju
             * authoritative occupancy period.
             */
            $managerTimeFieldsChanged =
                array_key_exists(
                    'site_manager_started_at',
                    $values,
                )
                || array_key_exists(
                    'site_manager_finished_at',
                    $values,
                );

            if ($managerTimeFieldsChanged) {
                $siteManagerStartedAt =
                    array_key_exists(
                        'site_manager_started_at',
                        $values,
                    )
                        ? $values['site_manager_started_at']
                        : $excavatorLog
                        ->site_manager_started_at;

                $siteManagerFinishedAt =
                    array_key_exists(
                        'site_manager_finished_at',
                        $values,
                    )
                        ? $values['site_manager_finished_at']
                        : $excavatorLog
                        ->site_manager_finished_at;

                $this->ensureExcavatorIsAvailable(
                    machineId:
                    $assignment->machine_id,
                    startedAt:
                    $siteManagerStartedAt,
                    finishedAt:
                    $siteManagerFinishedAt,
                    date:
                    $assignment->date->toDateString(),
                    exceptAssignmentId:
                    $assignment->id,
                );

                $this->ensureOperatorIsAvailable(
                    workerId:
                    $assignment->worker_id,
                    startedAt:
                    $siteManagerStartedAt,
                    finishedAt:
                    $siteManagerFinishedAt,
                    date:
                    $assignment->date->toDateString(),
                    exceptAssignmentId:
                    $assignment->id,
                );
            }

            $excavatorLog->update(
                $values
            );

            $this->logging->activity(
                actor: $currentWorker,
                subject: $excavatorLog,
                event:
                LogEvent::EXCAVATOR_LOG_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $excavatorLog,
                event:
                LogEvent::EXCAVATOR_LOG_UPDATED,
                oldValues:
                $oldValues,
                newValues:
                $excavatorLog
                    ->fresh()
                    ->getAttributes(),
                reason:
                $reason,
            );

            $excavatorLog->load(
                'machineAssignment.worker'
            );

            $assignment =
                $excavatorLog->machineAssignment;

            if (! $assignment) {
                throw new BusinessException(
                    'Excavator log does not have a machine assignment.'
                );
            }

            $this->syncMachineWorkerAttendanceAction->execute(
                assignment:
                $assignment,
                startedAt:
                $excavatorLog
                    ->site_manager_started_at,
                finishedAt:
                $excavatorLog
                    ->site_manager_finished_at,
                advancePayment:
                in_array(
                    'advance_payment',
                    $data->providedFields,
                    true,
                )
                    ? $data->advancePayment
                    : null,
            );

            if ($operatorStartedDay) {
                $this->notifyAdminsWorkerStartedDay(
                    excavatorLog:
                    $excavatorLog,
                    worker:
                    $currentWorker,
                );
            }

            if ($operatorFinishedDay) {
                $this->notifyAdminsWorkerFinishedDay(
                    excavatorLog:
                    $excavatorLog,
                    worker:
                    $currentWorker,
                );
            }

            return $excavatorLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
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
                'Excavator is already in use during the selected period.'
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
                'Operator is already assigned during the selected period.'
            );
        }
    }

    private function applyManagerConflictCondition(
        Builder $query,
        mixed $startedAt,
        mixed $finishedAt,
    ): void {
        if ($startedAt === null) {
            return;
        }

        $query->where(
            function (Builder $query) use (
                $startedAt,
                $finishedAt,
            ) {
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
                            if ($finishedAt !== null) {
                                $query->where(
                                    'site_manager_started_at',
                                    '<',
                                    $finishedAt,
                                );
                            }

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

    private function notifyAdminsWorkerStartedDay(
        ExcavatorLog $excavatorLog,
        Worker $worker,
    ): void {
        Worker::query()
            ->where(
                'company_id',
                $worker->company_id,
            )
            ->where(
                'role',
                WorkerRole::ADMIN,
            )
            ->where(
                'is_active',
                true,
            )
            ->each(
                function (Worker $admin) use (
                    $excavatorLog,
                    $worker,
                ) {
                    $notification =
                        $this->notifications
                            ->workerStartedDay(
                                worker: $admin,
                                startedBy: $worker,
                                machineAssignmentId:
                                $excavatorLog
                                    ->machine_assignment_id,
                                logType: 'excavator',
                                logId:
                                $excavatorLog->id,
                            );

                    $this->pushNotifications->send(
                        worker: $admin,
                        title: $notification->title,
                        body: $notification->message,
                        data: [
                            'type' =>
                                'worker_day_started',
                            'worker_type' =>
                                'operator',
                            'worker_id' =>
                                $worker->id,
                            'excavator_log_id' =>
                                $excavatorLog->id,
                            'machine_assignment_id' =>
                                $excavatorLog
                                    ->machine_assignment_id,
                            'notification_id' =>
                                $notification->id,
                        ],
                    );
                }
            );
    }

    private function notifyAdminsWorkerFinishedDay(
        ExcavatorLog $excavatorLog,
        Worker $worker,
    ): void {
        Worker::query()
            ->where(
                'company_id',
                $worker->company_id,
            )
            ->where(
                'role',
                WorkerRole::ADMIN,
            )
            ->where(
                'is_active',
                true,
            )
            ->each(
                function (Worker $admin) use (
                    $excavatorLog,
                    $worker,
                ) {
                    $notification =
                        $this->notifications
                            ->workerFinishedDay(
                                worker: $admin,
                                finishedBy: $worker,
                                machineAssignmentId:
                                $excavatorLog
                                    ->machine_assignment_id,
                                logType: 'excavator',
                                logId:
                                $excavatorLog->id,
                            );

                    $this->pushNotifications->send(
                        worker: $admin,
                        title: $notification->title,
                        body: $notification->message,
                        data: [
                            'type' =>
                                'worker_day_finished',
                            'worker_type' =>
                                'operator',
                            'worker_id' =>
                                $worker->id,
                            'excavator_log_id' =>
                                $excavatorLog->id,
                            'machine_assignment_id' =>
                                $excavatorLog
                                    ->machine_assignment_id,
                            'notification_id' =>
                                $notification->id,
                        ],
                    );
                }
            );
    }
}
