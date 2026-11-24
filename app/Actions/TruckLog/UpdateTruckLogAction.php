<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Enums\LogEvent;
use App\Enums\WorkerRole;
use App\Exceptions\BusinessException;
use App\Models\TruckLog;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;
use App\Traits\ManagesMachineAssignments;
use Illuminate\Support\Facades\Log;

class UpdateTruckLogAction extends BaseAction
{
    use ManagesMachineAssignments;

    public function __construct(
        private readonly LoggingService $logging,
        private readonly SyncMachineWorkerAttendanceAction $syncMachineWorkerAttendanceAction,
        private readonly NotificationService $notifications,
        private readonly WorkerPushNotificationService $pushNotifications,
    ) {}

    public function execute(
        TruckLog $truckLog,
        UpdateTruckLogData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): TruckLog {
        return $this->transaction(function () use (
            $truckLog,
            $data,
            $currentWorker,
            $reason,
        ) {
            $this->lockMachineAssignments($currentWorker);
            $truckLog = TruckLog::query()->findOrFail($truckLog->id);
            $assignment = $truckLog->machineAssignment;
            abort_unless($assignment, 404);
            $ownUpdate = $currentWorker->isDriver() && $truckLog->worker_id === $currentWorker->id;
            $dailyLog = $this->editableMachineDailyLog($assignment, $currentWorker, $ownUpdate);
            abort_if($ownUpdate && array_intersect(
                $data->providedFields,
                ['site_manager_started_at', 'site_manager_finished_at', 'note_site_manager', 'advance_payment'],
            ), 403);
            $oldValues =
                $truckLog->getAttributes();

            $values = [];

            Log::info(
                'UpdateTruckLogAction received',
                [
                    'TruckLog' => $truckLog->toArray(),
                ],
            );

            Log::info(
                'UpdateTruckLogData received',
                [
                    'UpdateTruckLogData' => $data,
                ],
            );

            Log::info(
                'Worker received',
                [
                    'Worker' => $currentWorker->toArray(),
                ],
            );

            if (in_array(
                'site_manager_started_at',
                $data->providedFields,
                true,
            )) {
                $values['site_manager_started_at'] =
                    $data->siteManagerStartedAt?->copy()->setTimezone(config('app.timezone'));
            }

            if (in_array(
                'site_manager_finished_at',
                $data->providedFields,
                true,
            )) {
                $values['site_manager_finished_at'] =
                    $data->siteManagerFinishedAt?->copy()->setTimezone(config('app.timezone'));
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
                'start_mileage',
                $data->providedFields,
                true,
            )) {
                $values['start_mileage'] =
                    $data->startMileage;
            }

            if (in_array(
                'end_mileage',
                $data->providedFields,
                true,
            )) {
                $values['end_mileage'] =
                    $data->endMileage;
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

            $startMileage =
                array_key_exists(
                    'start_mileage',
                    $values,
                )
                    ? $values['start_mileage']
                    : $truckLog->start_mileage;

            $endMileage =
                array_key_exists(
                    'end_mileage',
                    $values,
                )
                    ? $values['end_mileage']
                    : $truckLog->end_mileage;

            if (
                $startMileage !== null
                && $endMileage !== null
                && (float) $endMileage <
                (float) $startMileage
            ) {
                throw new BusinessException(
                    'End mileage cannot be less than start mileage.'
                );
            }

            $driverStartedDay =
                $currentWorker->isDriver()
                && $data->started === true;

            $driverFinishedDay =
                $currentWorker->isDriver()
                && $data->finished === true;

            $truckLog->load(
                'machineAssignment.worker'
            );

            $assignment =
                $truckLog->machineAssignment;

            if (! $assignment) {
                throw new BusinessException(
                    'Truck log does not have a machine assignment.'
                );
            }

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
                        : $truckLog
                            ->site_manager_started_at;

                $siteManagerFinishedAt =
                    array_key_exists(
                        'site_manager_finished_at',
                        $values,
                    )
                        ? $values['site_manager_finished_at']
                        : $truckLog
                            ->site_manager_finished_at;

                MachinePeriod::validateManagerTimes($siteManagerStartedAt, $siteManagerFinishedAt);

                MachinePeriod::ensureAvailable(
                    companyId: $currentWorker->company_id,
                    machineId: $assignment->machine_id,
                    workerId: $assignment->worker_id,
                    date: $assignment->date->toDateString(),
                    start: $siteManagerStartedAt,
                    finish: $siteManagerFinishedAt,
                    exceptAssignmentId: $assignment->id,
                    updating: true,
                    machineMessage: 'Truck is already in use during the selected period.',
                    workerMessage: 'Driver is already assigned during the selected period.',
                );
            }

            if ($managerTimeFieldsChanged && $siteManagerStartedAt !== null && $dailyLog !== null
                && $truckLog->site_manager_started_at === null && $assignment->isPendingSelfAssignment()) {
                $this->ensureManagerAttribution($currentWorker);
                $oldAssignment = $assignment->getAttributes();
                $assignment->update([
                    'daily_log_id' => $dailyLog->id,
                    'site_manager_id' => $currentWorker->id,
                ]);
                $this->logging->audit(
                    actor: $currentWorker,
                    subject: $assignment,
                    event: LogEvent::MACHINE_ASSIGNMENT_TAKEN_OVER,
                    oldValues: $oldAssignment,
                );
            }

            $truckLog->update(
                $values
            );

            $truckLog->load(
                'machineAssignment.worker'
            );

            $assignment =
                $truckLog->machineAssignment;

            if (! $assignment) {
                throw new BusinessException(
                    'Truck log does not have a machine assignment.'
                );
            }

            if (! $ownUpdate) {
                $this->syncMachineWorkerAttendanceAction->execute(
                    assignment: $assignment,
                    startedAt: $truckLog
                        ->site_manager_started_at,
                    finishedAt: $truckLog
                        ->site_manager_finished_at,
                    advancePayment: in_array(
                        'advance_payment',
                        $data->providedFields,
                        true,
                    )
                        ? $data->advancePayment
                        : null,
                    actor: $currentWorker,
                );
            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $truckLog,
                event: LogEvent::TRUCK_LOG_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $truckLog,
                event: LogEvent::TRUCK_LOG_UPDATED,
                oldValues: $oldValues,
                newValues: $truckLog
                    ->fresh()
                    ->getAttributes(),
                reason: $reason,
            );

            if ($driverStartedDay) {
                $this->notifyAdminsWorkerStartedDay(
                    truckLog: $truckLog,
                    worker: $currentWorker,
                );
            }

            if ($driverFinishedDay) {
                $this->notifyAdminsWorkerFinishedDay(
                    truckLog: $truckLog,
                    worker: $currentWorker,
                );
            }

            return $truckLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }

    private function notifyAdminsWorkerStartedDay(
        TruckLog $truckLog,
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
                    $truckLog,
                    $worker,
                ) {
                    $notification =
                        $this->notifications
                            ->workerStartedDay(
                                worker: $admin,
                                startedBy: $worker,
                                machineAssignmentId: $truckLog
                                    ->machine_assignment_id,
                                logType: 'truck',
                                logId: $truckLog->id,
                            );

                    $this->pushNotifications->send(
                        worker: $admin,
                        title: $notification->title,
                        body: $notification->message,
                        data: [
                            'type' => 'worker_day_started',
                            'worker_id' => $worker->id,
                            'log_type' => 'truck',
                            'truck_log_id' => $truckLog->id,
                            'worker_type' => 'driver',
                            'machine_assignment_id' => $truckLog
                                ->machine_assignment_id,
                            'notification_id' => $notification->id,
                        ],
                    );
                }
            );
    }

    private function notifyAdminsWorkerFinishedDay(
        TruckLog $truckLog,
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
                    $truckLog,
                    $worker,
                ) {
                    $notification =
                        $this->notifications
                            ->workerFinishedDay(
                                worker: $admin,
                                finishedBy: $worker,
                                machineAssignmentId: $truckLog
                                    ->machine_assignment_id,
                                logType: 'truck',
                                logId: $truckLog->id,
                            );

                    $this->pushNotifications->send(
                        worker: $admin,
                        title: $notification->title,
                        body: $notification->message,
                        data: [
                            'type' => 'worker_day_finished',
                            'worker_id' => $worker->id,
                            'log_type' => 'truck',
                            'truck_log_id' => $truckLog->id,
                            'worker_type' => 'driver',
                            'machine_assignment_id' => $truckLog
                                ->machine_assignment_id,
                            'notification_id' => $notification->id,
                        ],
                    );
                }
            );
    }
}
