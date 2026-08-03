<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SubcontractorLog */
class SubcontractorLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'subcontractor_id' => $this->subcontractor_id,
            'worker_count' => $this->worker_count,

            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,

            'note' => $this->note,

            'company_id' => $this->company_id,
            'daily_log_id' => $this->daily_log_id,
            'construction_site_id' => $this->construction_site_id,
            'site_manager_id' => $this->site_manager_id,

            'date' => $this->date,

            'subcontractor' => SubcontractorResource::make(
                $this->whenLoaded('subcontractor')
            ),

            'creator' => WorkerResource::make(
                $this->whenLoaded('creator')
            ),

            'site_manager' => WorkerResource::make(
                $this->whenLoaded('siteManager')
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
