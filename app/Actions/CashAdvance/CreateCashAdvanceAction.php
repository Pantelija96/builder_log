<?php

namespace App\Actions\CashAdvance;

use App\Actions\BaseAction;
use App\DTO\CashAdvance\CreateCashAdvanceData;
use App\Enums\LogEvent;
use App\Exceptions\BusinessException;
use App\Models\CashAdvance;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class CreateCashAdvanceAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        CreateCashAdvanceData $data,
        Worker $currentWorker,
    ): CashAdvance {

        return $this->transaction(function () use (
            $data,
            $currentWorker,
        ) {

            $siteManager = Worker::findOrFail(
                $data->siteManagerId
            );

            if (! $siteManager->isSiteManager()) {
                throw new BusinessException(
                    'Selected worker is not a site manager.'
                );
            }

            $cashAdvance = CashAdvance::create([
                'company_id' => $currentWorker->company_id,
                'site_manager_id' => $siteManager->id,
                'amount' => $data->amount,
                'date' => $data->date,
                'created_by' => $currentWorker->id,
            ]);

            $this->logging->audit(
                actor: $currentWorker,
                subject: $cashAdvance,
                event: LogEvent::CASH_ADVANCE_CREATED,
            );

            return $cashAdvance;
        });
    }
}
