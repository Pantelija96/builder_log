<?php

namespace App\Services;

use App\Actions\DeliveryNote\CreateDeliveryNoteAction;
use App\Actions\DeliveryNote\DeleteDeliveryNoteAction;
use App\Actions\DeliveryNote\UpdateDeliveryNoteAction;
use App\DTO\DeliveryNote\CreateDeliveryNoteData;
use App\DTO\DeliveryNote\GetDeliveryNotesData;
use App\DTO\DeliveryNote\UpdateDeliveryNoteData;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Worker;
use App\QueryFilters\DeliveryNoteFilter;
use Illuminate\Database\Eloquent\Collection;

class DeliveryNoteService
{
    public function __construct(
        private readonly CreateDeliveryNoteAction $createAction,
        private readonly UpdateDeliveryNoteAction $updateDeliveryNoteAction,
        private readonly DeleteDeliveryNoteAction $deleteDeliveryNoteAction,
    ) {
    }

    public function getAll(DailyLog $dailyLog, GetDeliveryNotesData $data,): Collection {
        $query = DeliveryNote::query()
            ->where('daily_log_id', $dailyLog->id)
            ->with([
                'supplier',
                'attachments',
            ]);

        $query = (new DeliveryNoteFilter($data))
            ->apply($query);

        return $query
            ->offset($data->list->offset)
            ->limit($data->list->limit)
            ->get();
    }

    public function create(DailyLog $dailyLog, CreateDeliveryNoteData $data, Worker $worker,): DeliveryNote {
        return $this->createAction->execute(
            $dailyLog,
            $data,
            $worker,
        );
    }

    public function update(
        DailyLog $dailyLog,
        DeliveryNote $deliveryNote,
        UpdateDeliveryNoteData $data,
        Worker $worker,
        ?string $reason,
    ): DeliveryNote {

        return $this->updateDeliveryNoteAction->execute(
            $dailyLog,
            $deliveryNote,
            $data,
            $worker,
            $reason,
        );
    }

    public function delete(
        DailyLog $dailyLog,
        DeliveryNote $deliveryNote,
        Worker $worker,
        string $reason,
    ): void {

        $this->deleteDeliveryNoteAction->execute(
            $dailyLog,
            $deliveryNote,
            $worker,
            $reason,
        );
    }
}
