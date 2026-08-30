<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteManagerOverallResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cash_advance_total' => $this['cash_advance_total'],
            'construction_site' => ConstructionSiteResource::make($this['construction_site']),
            'expenses' => ExpenseResource::collection($this['expenses']),
            'worker_attendance' => WorkerAttendanceResource::collection($this['worker_attendance']),
        ];
    }
}
