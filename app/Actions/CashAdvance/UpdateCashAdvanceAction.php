<?php

namespace App\Actions\CashAdvance;

use App\Actions\BaseAction;
use App\DTO\CashAdvance\UpdateCashAdvanceData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\CashAdvance;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class UpdateCashAdvanceAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        CashAdvance $cashAdvance,
        UpdateCashAdvanceData $data,
        Worker $currentWorker,
        ?string $reason = null,
    ): CashAdvance {

        return $this->transaction(function () use (
            $cashAdvance,
            $data,
            $currentWorker,
            $reason,
        ) {

            $siteManager = Worker::findOrFail(
                $data->siteManagerId
            );

            if (! $siteManager->isSiteManager()) {
                throw new BusinessException(
                    'Selected worker is not a site manager.'
                );
            }

            $oldValues = $cashAdvance->getOriginal();

            $cashAdvance->update([
                'site_manager_id' => $siteManager->id,
                'amount' => $data->amount,
                'date' => $data->date,
            ]);

            $this->logging->audit(
                actor: $currentWorker,
                subject: $cashAdvance,
                event: LogEvent::CASH_ADVANCE_UPDATED,
                oldValues: $oldValues,
                reason: $reason,
            );

            return $cashAdvance->refresh();
        });
    }
}
