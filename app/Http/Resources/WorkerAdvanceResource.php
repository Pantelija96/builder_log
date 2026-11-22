<?php

namespace App\Http\Resources;

use App\Models\WorkerAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkerAttendance
 */
class WorkerAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'worker' => WorkerResource::make(
                $this->whenLoaded('worker')
            ),

            'date' => $this->date,

            'advance_payment' => $this->advance_payment,
        ];
    }
}
