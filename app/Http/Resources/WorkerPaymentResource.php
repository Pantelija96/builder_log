<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\WorkerPayment
 */
class WorkerPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company_id' => $this->company_id,
            'worker_id' => $this->worker_id,

            'amount' => $this->amount,

            'date' => $this->date?->toDateString(),

            'note' => $this->note,

            'worker' => WorkerResource::make(
                $this->whenLoaded('worker')
            ),

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
