<?php

namespace App\Actions\CashAdvance;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\CashAdvance;
use App\Models\Worker;
use App\Services\Logging\LoggingService;

class DeleteCashAdvanceAction extends BaseAction
{
    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        CashAdvance $cashAdvance,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->transaction(function () use (
            $cashAdvance,
            $currentWorker,
            $reason,
        ) {

            $oldValues = $cashAdvance->attributesToArray();

            $cashAdvance->delete();

            $this->logging->audit(
                actor: $currentWorker,
                subject: $cashAdvance,
                event: LogEvent::CASH_ADVANCE_DELETED,
                oldValues: $oldValues,
                reason: $reason,
            );
        });
    }
}
