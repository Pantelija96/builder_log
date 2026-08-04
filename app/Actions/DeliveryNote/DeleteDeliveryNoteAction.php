<?php

namespace App\Actions\DeliveryNote;

use App\Actions\BaseAction;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class DeleteDeliveryNoteAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
    ) {
    }

    public function execute(
        DailyLog $dailyLog,
        DeliveryNote $deliveryNote,
        Worker $currentWorker,
        string $reason,
    ): void {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        $this->transaction(function () use (
            $deliveryNote,
            $currentWorker,
            $reason
        ) {

            $this->logging->audit(
                actor: $currentWorker,
                subject: $deliveryNote,
                event: LogEvent::DELIVERY_NOTE_DELETED,
                oldValues: $deliveryNote->getRawOriginal(),
                reason: $reason,
            );

            $deliveryNote->delete();
        });
    }
}
