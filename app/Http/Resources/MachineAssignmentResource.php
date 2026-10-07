<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MachineAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine' => MachineResource::make($this->whenLoaded('machine')),
            'construction_site' => ConstructionSiteResource::make($this->whenLoaded('constructionSite')),
            'site_manager' => WorkerResource::make($this->whenLoaded('siteManager')),
            'worker' => WorkerResource::make($this->whenLoaded('worker')),
            'creator' => WorkerResource::make($this->whenLoaded('creator')),
            'excavator_log' => $this->getExcavatorLogResource(),
            'truck_log' => $this->getTruckLogResource(),
            'date' => $this->date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'is_deleted' => $this->trashed(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    private function getExcavatorLogResource(): mixed
    {
        if ($this->relationLoaded('excavatorLogWithTrashed')) {
            return ExcavatorLogResource::make(
                $this->excavatorLogWithTrashed
            );
        }

        if ($this->relationLoaded('excavatorLog')) {
            return ExcavatorLogResource::make(
                $this->excavatorLog
            );
        }

        return null;
    }

    private function getTruckLogResource(): mixed
    {
        if ($this->relationLoaded('truckLogWithTrashed')) {
            return TruckLogResource::make(
                $this->truckLogWithTrashed
            );
        }

        if ($this->relationLoaded('truckLog')) {
            return TruckLogResource::make(
                $this->truckLog
            );
        }

        return null;
    }
}
