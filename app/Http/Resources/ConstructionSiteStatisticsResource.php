<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConstructionSiteStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'construction_site_id' => $this->resource['construction_site_id'],

            'date_from' => $this->resource['date_from'],
            'date_to' => $this->resource['date_to'],

            'total_costs' => $this->resource['total_costs'],

            'expenses' => $this->resource['expenses'],
            'worker_advances' => $this->resource['worker_advances'],

            'worker_hours' => $this->resource['worker_hours'],
            'subcontractor_hours' => $this->resource['subcontractor_hours'],
            'machine_hours' => $this->resource['machine_hours'],
        ];
    }
}
