<?php

namespace App\Actions\ExcavatorLog;

use App\Actions\BaseAction;
use App\DTO\ExcavatorLog\UpdateExcavatorLogData;
use App\Enums\LogEvent;
use App\Models\ExcavatorLog;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class UpdateExcavatorLogAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {}

    public function execute(ExcavatorLog $excavatorLog, UpdateExcavatorLogData $data, Worker $currentWorker, ?string $reason = null,): ExcavatorLog
    {
        return $this->transaction(function () use (
            $excavatorLog,
            $data,
            $currentWorker,
            $reason,
        ) {

            $oldValues = $excavatorLog->getAttributes();
            $values = [];

            /*
             * Site Manager vremena
             */

            if (in_array('site_manager_started_at', $data->providedFields, true,))
            {
                $values['site_manager_started_at'] = $data->siteManagerStartedAt;
            }

            if (in_array('site_manager_finished_at', $data->providedFields, true,))
            {
                $values['site_manager_finished_at'] = $data->siteManagerFinishedAt;
            }

            /*
             * Operator vremena
             */

            if (in_array('operator_started_at', $data->providedFields, true,))
            {
                $values['operator_started_at'] = $data->operatorStartedAt;
            }

            if (in_array('operator_finished_at', $data->providedFields, true,))
            {
                $values['operator_finished_at'] = $data->operatorFinishedAt;
            }

            /*
             * Ako operator zavrsi sa masinom, a sef gradilista nije oznacio kad masina zavrsava
             * to operatorovo vreme ce se koristiti kao kraj koriscenja masine
             */
            if (in_array('operator_finished_at', $data->providedFields, true,)
                && $data->operatorFinishedAt !== null
                && $excavatorLog->site_manager_finished_at === null
                && ! in_array('site_manager_finished_at', $data->providedFields, true,))
            {
                $values['site_manager_finished_at'] = $data->operatorFinishedAt;
            }

            /*
             * Work data
             */

            if (in_array('work_hours', $data->providedFields, true,))
            {
                $values['work_hours'] = $data->workHours;
            }

            if (in_array('fuel_added', $data->providedFields, true,))
            {
                $values['fuel_added'] = $data->fuelAdded;
            }

            if (in_array('fuel_remaining', $data->providedFields, true,))
            {
                $values['fuel_remaining'] = $data->fuelRemaining;
            }

            /*
             * Notes
             */

            if (in_array('note_site_manager', $data->providedFields, true,))
            {
                $values['note_site_manager'] = $data->noteSiteManager;
            }

            if (in_array('note_operator', $data->providedFields, true,))
            {
                $values['note_operator'] = $data->noteOperator;
            }

            /*
             * Update
             */

            $excavatorLog->update($values);

            $this->logging->activity(
                actor: $currentWorker,
                subject: $excavatorLog,
                event: LogEvent::EXCAVATOR_LOG_UPDATED,
            );

            $this->logging->audit(
                actor: $currentWorker,
                subject: $excavatorLog,
                event: LogEvent::EXCAVATOR_LOG_UPDATED,
                oldValues: $oldValues,
                newValues: $excavatorLog->fresh()->getAttributes(),
                reason: $reason,
            );

            return $excavatorLog->fresh([
                'machineAssignment',
                'worker',
                'creator',
            ]);
        });
    }
}
