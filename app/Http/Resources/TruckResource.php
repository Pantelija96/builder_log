<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TruckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine_id' => $this->machine_id,
            'initial_mileage' => $this->initial_mileage,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
