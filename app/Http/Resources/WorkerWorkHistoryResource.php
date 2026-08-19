<?php

namespace App\Http\Resources;

use App\DTO\Worker\WorkerWorkHistoryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerWorkHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var WorkerWorkHistoryData $data */
        $data = $this->resource;

        $history = match ($data->type) {
            'attendance' => WorkerAttendanceResource::collection(
                $data->history
            ),
            'excavator_log' => ExcavatorLogResource::collection(
                $data->history
            ),
            'truck_log' => TruckLogResource::collection(
                $data->history
            ),
            default => [],
        };

        return [
            'worker' => WorkerResource::make(
                $data->worker
            ),
            'type' => $data->type,
            'history' => $history,
        ];
    }
}
