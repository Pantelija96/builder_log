<?php

namespace App\Actions\TruckLog;

use App\Actions\BaseAction;
use App\DTO\TruckLog\UpdateTruckLogData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\TruckLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class UpdateTruckLogAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

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

            $oldValues = $truckLog->getAttributes();
            $siteManagerFinishedAt = $data->siteManagerFinishedAt;

            if (
                in_array(
                    'operator_finished_at',
                    $data->providedFields,
                    true
                )
                && $data->operatorFinishedAt !== null
                && $truckLog->site_manager_finished_at === null
                && ! in_array(
                    'site_manager_finished_at',
                    $data->providedFields,
                    true
                )
            ) {
                $siteManagerFinishedAt = $data->operatorFinishedAt;
            }

            $finalStartMileage = in_array(
                'start_mileage',
                $data->providedFields,
                true
            )
                ? $data->startMileage
                : $truckLog->start_mileage;

            $finalEndMileage = in_array(
                'end_mileage',
                $data->providedFields,
                true
            )
                ? $data->endMileage
                : $truckLog->end_mileage;

            if (
                $finalStartMileage !== null
                && $finalEndMileage !== null
                && $finalEndMileage < $finalStartMileage
            ) {
                throw new BusinessException(
                    __('End mileage must be greater than or equal to start mileage.')
                );
            }

            $updates = [];

            if (
                in_array(
                    'site_manager_started_at',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['site_manager_started_at'] =
                    $data->siteManagerStartedAt;
            }

            if (
                in_array(
                    'site_manager_finished_at',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['site_manager_finished_at'] =
                    $siteManagerFinishedAt;
            }

            if (
                in_array(
                    'operator_started_at',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['operator_started_at'] =
                    $data->operatorStartedAt;
            }

            if (
                in_array(
                    'operator_finished_at',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['operator_finished_at'] =
                    $data->operatorFinishedAt;
            }

            if (
                in_array(
                    'start_mileage',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['start_mileage'] =
                    $data->startMileage;
            }

            if (
                in_array(
                    'end_mileage',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['end_mileage'] =
                    $data->endMileage;
            }

            if (
                in_array(
                    'fuel_added',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['fuel_added'] = $data->fuelAdded;
            }

            if (
                in_array(
                    'fuel_remaining',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['fuel_remaining'] =
                    $data->fuelRemaining;
            }

            if (
                in_array(
                    'note',
                    $data->providedFields,
                    true
                )
            ) {
                $updates['note'] =
                    $data->note;
            }

            $truckLog->update($updates);

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
                'machine',
                'worker',
                'creator',
            ]);
        });
    }
}
