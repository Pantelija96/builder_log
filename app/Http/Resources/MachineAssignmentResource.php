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
            'date' => $this->date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
