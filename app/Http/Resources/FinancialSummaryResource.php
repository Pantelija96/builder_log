<?php

namespace App\Http\Resources;

use App\DTO\Financial\FinancialSummaryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FinancialSummaryData
 */
class FinancialSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'site_manager' => WorkerResource::make(
                $this->siteManager,
            ),
            'advanced' => $this->advanced,
            'spent' => $this->spent,
            'remaining' => $this->remaining,
            'utilization' => $this->utilization,
        ];
    }
}
