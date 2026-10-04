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
            'excavatorLog' => ExcavatorLogResource::make($this->whenLoaded('excavatorLog')),
            'truckLog' => TruckLogResource::make($this->whenLoaded('truckLog')),
            'date' => $this->date?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'is_deleted' => $this->trashed(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
