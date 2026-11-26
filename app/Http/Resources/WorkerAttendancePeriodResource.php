<?php

namespace App\Http\Resources;

use App\Models\WorkerAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkerAttendance */
class WorkerAttendancePeriodResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'daily_log_id' => $this->daily_log_id,
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'construction_site_id' => $this->construction_site_id,
            'construction_site' => $this->constructionSite === null ? null : ConstructionSiteResource::make($this->constructionSite),
            'site_manager_id' => $this->site_manager_id,
            'created_by' => $this->created_by,
            'creator' => $this->creator === null ? null : WorkerResource::make($this->creator),
            'machine_assignment_id' => $this->machine_assignment_id,
        ];
    }
}
