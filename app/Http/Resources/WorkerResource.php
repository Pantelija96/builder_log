<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_order' => $this->display_order,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'hourly_rate' => $this->hourly_rate,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'is_available' => $this->is_available && (
                (! $this->isOperator() && ! $this->isDriver())
                || (! $this->has_occupied_machine && ! $this->has_current_attendance)
            ),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'construction_sites' => ConstructionSiteResource::collection($this->whenLoaded('constructionSites')),
        ];
    }
}
