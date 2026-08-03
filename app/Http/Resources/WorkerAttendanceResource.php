<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerAttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'company_id' => $this->company_id,

            'daily_log_id' => $this->daily_log_id,

            'construction_site_id' => $this->construction_site_id,

            'site_manager_id' => $this->site_manager_id,

            'worker_id' => $this->worker_id,

            'date' => $this->date,

            'started_at' => $this->started_at,

            'finished_at' => $this->finished_at,

            'worked_time' => $this->worked_time,

            'advance_payment' => $this->advance_payment,

            'created_by' => $this->created_by,

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
