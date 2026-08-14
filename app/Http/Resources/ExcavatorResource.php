<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExcavatorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'machine_id' => $this->machine_id,
            'initial_work_hours' => $this->initial_work_hours,
            'total_work_hours' => $this->total_work_hours,
            'machine' => MachineResource::make($this->whenLoaded('machine')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
