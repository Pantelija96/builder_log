<?php

namespace App\Services;

use App\Actions\DeliveryNote\CreateDeliveryNoteAction;
use App\DTO\DeliveryNote\CreateDeliveryNoteData;
use App\DTO\DeliveryNote\GetDeliveryNotesData;
use App\Models\DailyLog;
use App\Models\DeliveryNote;
use App\Models\Worker;
use App\QueryFilters\DeliveryNoteFilter;
use Illuminate\Database\Eloquent\Collection;

class DeliveryNoteService
{
    public function __construct(
        private readonly CreateDeliveryNoteAction $createAction,
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
}
