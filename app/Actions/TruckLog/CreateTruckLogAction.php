<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\DTO\TruckLog\CreateTruckLogData;
use App\DTO\TruckLog\CreateTruckLogForDriverData;
use App\Enums\LogEvent;
use App\Enums\MachineType;
use App\Exceptions\BusinessException;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Machine;
use App\Models\MachineAssignment;
use App\Models\TruckLog;
use App\Models\Worker;
use App\QueryFilters\MachinePeriod;
use App\Services\Logging\LoggingService;
use App\Services\NotificationService;
use App\Services\WorkerPushNotificationService;
use App\Traits\ManagesMachineAssignments;
use Illuminate\Database\Eloquent\Builder;

class CreateTruckLogAction extends BaseAction
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
        CreateTruckLogData $data,
        Worker $currentWorker,
    ): TruckLog {
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

            $machine = $this->findTruck(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $worker = $this->findDriver(
                workerId: $data->workerId,
                companyId: $currentWorker->company_id,
            );

            $startedAt =
                $data->siteManagerStartedAt ?? now();

            $finishedAt =
                $data->siteManagerFinishedAt;

            $selfAssignment =
                $this->findSelfAssignmentForTakeover(
                    companyId: $currentWorker->company_id,
                    machineId: $machine->id,
                    workerId: $worker->id,
                    dailyLog: $dailyLog,
                    date: $dailyLog->date->toDateString(),
                    startedAt: $startedAt,
                    finishedAt: $finishedAt,
                );

            if ($selfAssignment) {
                $truckLog =
                    $selfAssignment->truckLog;

                if (! $truckLog) {
                    throw new BusinessException(
                        'Truck assignment does not have a truck log.'
                    );
                }

                MachinePeriod::ensureAvailable(
                    companyId: $currentWorker->company_id,
                    machineId: $machine->id,
                    workerId: $worker->id,
                    date: $dailyLog->date->toDateString(),
                    start: $startedAt,
                    finish: $finishedAt,
                    exceptAssignmentId: $selfAssignment->id,
                    machineMessage: 'Truck is already in use during the selected period.',
                    workerMessage: 'Driver is already assigned to another truck during the selected period.',
                );

                $oldValues = $selfAssignment->getAttributes();
                $selfAssignment->update([
                    'daily_log_id' => $dailyLog->id,
                    'construction_site_id' => $dailyLog->construction_site_id,
                    'site_manager_id' => $currentWorker->id,
                ]);

                $truckLog->update([
                    'site_manager_started_at' => $data->siteManagerStartedAt,
                    'site_manager_finished_at' => $data->siteManagerFinishedAt,
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
                    startedAt: $data->siteManagerStartedAt,
                    finishedAt: $data->siteManagerFinishedAt,
                    advancePayment: $data->advancePayment,
                    actor: $currentWorker,
                );

                if ($currentWorker->isSiteManager()) {
                    $this->notifyDriver(
                        assignment: $selfAssignment,
                        driver: $worker,
                        assignedBy: $currentWorker,
                    );
                }

                return $truckLog->fresh([
                    'machineAssignment',
                    'worker',
                    'creator',
                ]);
            }

            MachinePeriod::ensureAvailable(
                companyId: $currentWorker->company_id,
                machineId: $machine->id,
                workerId: $worker->id,
                date: $dailyLog->date->toDateString(),
                start: $startedAt,
                finish: $finishedAt,
                machineMessage: 'Truck is already in use during the selected period.',
                workerMessage: 'Driver is already assigned to another truck during the selected period.',
            );

            $machineAssignment =
                MachineAssignment::create([
                    'company_id' => $currentWorker->company_id,
                    'daily_log_id' => $dailyLog->id,
                    'construction_site_id' => $dailyLog->construction_site_id,
                    'site_manager_id' => $currentWorker->id,
                    'machine_id' => $machine->id,
                    'worker_id' => $worker->id,
                    'date' => $dailyLog->date,
                    'created_by' => $currentWorker->id,
                ]);

            $truckLog = TruckLog::create([
                'machine_assignment_id' => $machineAssignment->id,
                'worker_id' => $worker->id,
                'created_by' => $currentWorker->id,

                'site_manager_started_at' => $data->siteManagerStartedAt,
                'site_manager_finished_at' => $data->siteManagerFinishedAt,

                'operator_started_at' => null,
                'operator_finished_at' => null,

                'start_mileage' => null,
                'end_mileage' => null,

                'fuel_added' => null,
                'fuel_remaining' => null,

                'note_site_manager' => $data->noteSiteManager,
                'note_operator' => null,
            ]);

            $this->syncMachineWorkerAttendanceAction->execute(
                assignment: $machineAssignment->fresh('worker'),
                startedAt: $data->siteManagerStartedAt,
                finishedAt: $data->siteManagerFinishedAt,
                advancePayment: $data->advancePayment,
                actor: $currentWorker,
            );

            if ($currentWorker->isSiteManager()) {
                $this->notifyDriver(
                    assignment: $machineAssignment,
                    driver: $worker,
                    assignedBy: $currentWorker,
                );
            }

            return $truckLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }

    public function executeForDriver(
        CreateTruckLogForDriverData $data,
        Worker $currentWorker,
    ): TruckLog {
        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {
            $this->lockMachineAssignments($currentWorker);
            if (! $currentWorker->isDriver()) {
                abort(403,
                    'Only drivers can assign trucks to themselves.'
                );
            }

            $constructionSite =
                ConstructionSite::query()
                    ->where(
                        'company_id',
                        $currentWorker->company_id,
                    )
                    ->find(
                        $data->constructionSiteId,
                    );

            abort_unless($constructionSite, 404);

            $machine = $this->findTruck(
                machineId: $data->machineId,
                companyId: $currentWorker->company_id,
            );

            $startedAt =
                $data->operatorStartedAt ?? now();

            $finishedAt =
                $data->operatorFinishedAt;

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
                start: $startedAt,
                finish: $finishedAt,
                machineMessage: 'Truck is already in use during the selected period.',
                workerMessage: 'Driver is already assigned to another truck during the selected period.',
            );

            $machineAssignment =
                MachineAssignment::create([
                    'company_id' => $currentWorker->company_id,
                    'daily_log_id' => $dailyLog?->id,
                    'construction_site_id' => $constructionSite->id,
                    'site_manager_id' => null,
                    'machine_id' => $machine->id,
                    'worker_id' => $currentWorker->id,
                    'date' => today(),
                    'created_by' => $currentWorker->id,
                ]);

            $truckLog = TruckLog::create([
                'machine_assignment_id' => $machineAssignment->id,
                'worker_id' => $currentWorker->id,
                'created_by' => $currentWorker->id,

                'site_manager_started_at' => null,
                'site_manager_finished_at' => null,

                'operator_started_at' => $data->operatorStartedAt,
                'operator_finished_at' => $data->operatorFinishedAt,

                'start_mileage' => null,
                'end_mileage' => null,

                'fuel_added' => null,
                'fuel_remaining' => null,

                'note_site_manager' => null,
                'note_operator' => $data->noteOperator,
            ]);

            return $truckLog->fresh([
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
                'truckLog',
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
            ->with('truckLog')
            ->latest('id')
            ->first();

        return $assignment?->isPendingSelfAssignment() ? $assignment : null;
    }

    private function notifyDriver(
        MachineAssignment $assignment,
        Worker $driver,
        Worker $assignedBy,
    ): void {
        $notification =
            $this->notifications->machineAssigned(
                worker: $driver,
                assignment: $assignment,
                assignedBy: $assignedBy,
                machineType: 'truck',
            );

        $this->pushNotifications->send(
            worker: $driver,
            title: $notification->title,
            body: $notification->message,
            data: [
                'type' => 'truck_assigned',
                'machine_assignment_id' => $assignment->id,
                'machine_id' => $assignment->machine_id,
                'construction_site_id' => $assignment->construction_site_id,
                'notification_id' => $notification->id,
            ],
        );
    }

    private function findTruck(
        int $machineId,
        int $companyId,
    ): Machine {
        $machine = Machine::query()
            ->where(
                'company_id',
                $companyId,
            )
            ->find(
                $machineId,
            );

        abort_unless($machine, 404);

        if ($machine->type !== MachineType::TRUCK) {
            throw new BusinessException(
                'Selected machine is not a truck.'
            );
        }

        if (! $machine->isActive()) {
            throw new BusinessException(
                'Truck is not active.'
            );
        }

        return $machine;
    }

    private function findDriver(
        int $workerId,
        int $companyId,
    ): Worker {
        $worker = Worker::query()
            ->where(
                'company_id',
                $companyId,
            )
            ->find(
                $workerId,
            );

        abort_unless($worker, 404);

        if (! $worker->isDriver()) {
            throw new BusinessException(
                'Selected worker is not a driver.'
            );
        }

        return $worker;
    }
}
