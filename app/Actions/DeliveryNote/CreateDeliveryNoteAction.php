<?php

namespace App\Actions\DeliveryNote;

use App\Actions\Attachment\UploadAttachmentsAction;
use App\Actions\BaseAction;
use App\DTO\DeliveryNote\CreateDeliveryNoteData;
use App\Enums\LogEvent;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Worker;
use App\Services\Logging\LoggingService;
use App\Traits\EnsuresDailyLogIsEditable;
use App\Traits\EnsuresWorkerCanManageDailyLog;

class CreateDeliveryNoteAction extends BaseAction
{
    use EnsuresDailyLogIsEditable;
    use EnsuresWorkerCanManageDailyLog;

    public function __construct(
        private readonly LoggingService $logging,
        private readonly UploadAttachmentsAction $uploadAttachmentsAction,
    ) {
    }

    public function execute(
        DailyLog $dailyLog,
        CreateDeliveryNoteData $data,
        Worker $currentWorker,
    ): DeliveryNote {

        $this->ensureEditable($dailyLog);
        $this->ensureCanModify($dailyLog, $currentWorker);

        return $this->transaction(function () use (
            $dailyLog,
            $data,
            $currentWorker,
        ) {

            $deliveryNote = DeliveryNote::create([
                'company_id' => $dailyLog->company_id,
                'daily_log_id' => $dailyLog->id,
                'construction_site_id' => $dailyLog->construction_site_id,
                'site_manager_id' => $dailyLog->site_manager_id,
                'supplier_id' => $data->supplierId,
                'name' => $data->name,
                'description' => $data->description,
                'date' => $dailyLog->date,
                'created_by' => $currentWorker->id,
            ])->refresh();

            if (! empty($data->attachments)) {

                $this->uploadAttachmentsAction->execute(
                    attachable: $deliveryNote,
                    files: $data->attachments,
                    worker: $currentWorker,
                );

            }

            $this->logging->activity(
                actor: $currentWorker,
                subject: $deliveryNote,
                event: LogEvent::DELIVERY_NOTE_CREATED,
            );

            return $deliveryNote;
        });
    }
}
