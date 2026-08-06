<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CashAdvance
 */
class CashAdvanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [

            'id' => $this->id,

            'company_id' => $this->company_id,

            'site_manager_id' => $this->site_manager_id,

            'amount' => $this->amount,

            'date' => $this->date,

            'site_manager' => WorkerResource::make(
                $this->whenLoaded('siteManager')
            ),

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
