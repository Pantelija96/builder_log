<?php

namespace App\Actions\DeliveryNote;

use App\Actions\BaseAction;
use App\DTO\DeliveryNote\UpdateDeliveryNoteData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class UpdateDeliveryNoteAction extends BaseAction
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
        UpdateDeliveryNoteData $data,
        Worker $currentWorker,
        ?string $reason,
    ): DeliveryNote {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        return $this->transaction(function () use (
            $deliveryNote,
            $data,
            $currentWorker,
            $reason
        ) {

            $oldValues = $deliveryNote->getRawOriginal();

            $deliveryNote->update([
                'supplier_id' => $data->supplierId,
                'name' => $data->name,
                'description' => $data->description,
            ]);

            $this->logging->audit(
                actor: $currentWorker,
                subject: $deliveryNote,
                event: LogEvent::DELIVERY_NOTE_UPDATED,
                oldValues: $oldValues,
                newValues: $deliveryNote->fresh()->getAttributes(),
                reason: $reason,
            );

            return $deliveryNote->fresh([
                'supplier',
                'creator',
                'siteManager',
                'attachments',
            ]);
        });
    }
}
