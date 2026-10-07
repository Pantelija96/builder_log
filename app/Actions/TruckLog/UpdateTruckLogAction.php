<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\Actions\WorkerAttendance\SyncMachineWorkerAttendanceAction;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use Illuminate\Support\Facades\Log;

class UpdateTruckLogAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
        private readonly SyncMachineWorkerAttendanceAction $syncMachineWorkerAttendanceAction,
    ) {}

    public function execute(TruckLog $truckLog, UpdateTruckLogData $data, Worker $currentWorker, ?string $reason = null,): TruckLog {
        return $this->transaction(function () use ($truckLog, $data, $currentWorker, $reason,) {
            $oldValues = $truckLog->getAttributes();
            $values = [];

            Log::info('UpdateTruckLogAction received', [
                'TruckLog' => $truckLog->toArray(),
            ]);

            Log::info('UpdateTruckLogData received', [
                'UpdateTruckLogData' => $data,
            ]);

            Log::info('Worker received', [
                'Worker' => $currentWorker->toArray(),
            ]);

            if (in_array('site_manager_started_at', $data->providedFields, true,)) {
                $values['site_manager_started_at'] =
                    $data->siteManagerStartedAt;
            }

            if (in_array('site_manager_finished_at', $data->providedFields, true,)) {
                $values['site_manager_finished_at'] =
                    $data->siteManagerFinishedAt;
            }

            if (in_array('operator_started_at', $data->providedFields, true,)) {
                $values['operator_started_at'] =
                    $data->operatorStartedAt;
            }

            if (in_array('operator_finished_at', $data->providedFields, true,)) {
                $values['operator_finished_at'] =
                    $data->operatorFinishedAt;
            }

            if (in_array('start_mileage', $data->providedFields, true,)) {
                $values['start_mileage'] = $data->startMileage;
            }

            if (in_array('end_mileage', $data->providedFields, true,)) {
                $values['end_mileage'] = $data->endMileage;
            }

            if (in_array('fuel_added', $data->providedFields, true,)) {
                $values['fuel_added'] = $data->fuelAdded;
            }

            if (in_array('fuel_remaining', $data->providedFields, true,)) {
                $values['fuel_remaining'] = $data->fuelRemaining;
            }

            if (in_array('note_site_manager', $data->providedFields, true,)) {
                $values['note_site_manager'] =
                    $data->noteSiteManager;
            }

            if (in_array('note_operator', $data->providedFields, true,)) {
                $values['note_operator'] =
                    $data->noteOperator;
            }

            $startMileage = array_key_exists('start_mileage', $values,) ? $values['start_mileage'] : $truckLog->start_mileage;

            $endMileage = array_key_exists('end_mileage', $values,) ? $values['end_mileage'] : $truckLog->end_mileage;

            if ($startMileage !== null && $endMileage !== null && (float) $endMileage < (float) $startMileage) {
                throw new BusinessException(
                    'End mileage cannot be less than start mileage.'
                );
            }

            $truckLog->update($values);

            $truckLog->load('machineAssignment.worker');

            $assignment = $truckLog->machineAssignment;

            if (! $assignment) {
                throw new BusinessException(
                    'Truck log does not have a machine assignment.'
                );
            }

            $this->syncMachineWorkerAttendanceAction->execute(
                assignment: $assignment,
                startedAt: $truckLog->site_manager_started_at,
                finishedAt: $truckLog->site_manager_finished_at,
                advancePayment: in_array('advance_payment', $data->providedFields, true,) ? $data->advancePayment : null,
            );

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
                newValues: $truckLog->fresh()->getAttributes(),
                reason: $reason,
            );

            return $truckLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }
}
