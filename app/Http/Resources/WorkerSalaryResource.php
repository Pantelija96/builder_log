<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerSalaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'worker_id' => $this->resource['worker_id'],
            'earned' => number_format($this->resource['earned'], 2, '.', ''),
            'advances' => number_format($this->resource['advances'], 2, '.', ''),
            'payments' => number_format($this->resource['payments'], 2, '.', ''),
            'outstanding' => number_format($this->resource['outstanding'], 2, '.', ''),
            'has_estimated_hours' => $this->resource['has_estimated_hours'],
            'estimated_attendances' => $this->resource['estimated_attendances'],
            'has_missing_hourly_rates' => $this->resource['has_missing_hourly_rates'],
            'missing_hourly_rate_attendances' => $this->resource['missing_hourly_rate_attendances'],
            'attendances' => WorkerAttendanceResource::collection($this->resource['attendances']),
            'can_pay' => $this->resource['can_pay'],
        ];
    }
}
