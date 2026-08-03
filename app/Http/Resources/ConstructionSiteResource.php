<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstructionSiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company_id' => $this->company_id,

            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,

            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'status' => $this->status,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'can_select' => $this->can_select,

            'company' => new CompanyResource(
                $this->whenLoaded('company')
            ),
        ];
    }
}
