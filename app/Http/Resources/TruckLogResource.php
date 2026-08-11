<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'machine' => MachineResource::make(
                $this->whenLoaded('machine')
            ),
            'worker' => WorkerResource::make(
                $this->whenLoaded('worker')
            ),
            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),
            'site_manager_started_at' => $this->site_manager_started_at?->toISOString(),
            'site_manager_finished_at' => $this->site_manager_finished_at?->toISOString(),
            'operator_started_at' => $this->operator_started_at?->toISOString(),
            'operator_finished_at' => $this->operator_finished_at?->toISOString(),
            'start_mileage' => $this->start_mileage,
            'end_mileage' => $this->end_mileage,
            'fuel_added' => $this->fuel_added,
            'fuel_remaining' => $this->fuel_remaining,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
