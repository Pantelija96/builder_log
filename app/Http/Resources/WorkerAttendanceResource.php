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
            'date' => $this->date?->toDateString(),
            'started_at' => $this->started_at,
            'finished_at' => $this->finished_at,
            'worked_time' => $this->worked_time,
            'advance_payment' => $this->advance_payment,
            'hourly_rate' => $this->hourly_rate,
            /*
            |--------------------------------------------------------------------------
            | Salary calculation
            |--------------------------------------------------------------------------
            |
            | These attributes are added at runtime by WorkerSalaryService.
            | They are only returned when attendance is part of salary calculation.
            |
            */
            'worked_hours' => $this->when(
                $this->resource->offsetExists('calculated_worked_hours'),
                fn () => number_format((float) $this->calculated_worked_hours, 2, '.', '')
            ),
            'is_estimated' => $this->when(
                $this->resource->offsetExists('is_estimated'),
                fn () => (bool) $this->is_estimated
            ),
            'has_hourly_rate' => $this->when(
                $this->resource->offsetExists('has_hourly_rate'),
                fn () => (bool) $this->has_hourly_rate
            ),
            'earned' => $this->when(
                $this->resource->offsetExists('calculated_earned'),
                fn () => $this->calculated_earned !== null ? number_format((float) $this->calculated_earned, 2, '.', '') : null
            ),
            'balance_effect' => $this->when(
                $this->resource->offsetExists('calculated_balance_effect'),
                fn () => $this->calculated_balance_effect !== null
                    ? number_format((float) $this->calculated_balance_effect, 2, '.', '') : null
            ),
            'created_by' => $this->created_by,
            'worker' => WorkerResource::make($this->whenLoaded('worker')),
            'creator' => WorkerResource::make($this->whenLoaded('creator')),
            'construction_site' => ConstructionSiteResource::make($this->whenLoaded('constructionSite')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'machine_assignment_id' => $this->machine_assignment_id,
            'machine' => $this->whenLoaded(
                'machineAssignment',
                function () {
                    if (! $this->machineAssignment) {
                        return null;
                    }

                    return MachineResource::make($this->machineAssignment->machine);
                }
            ),
        ];
    }
}
