<?php

namespace App\DTO\Financial;

use App\Models\Worker;

readonly class FinancialSummaryData
{
    public function __construct(
        public ?Worker $siteManager,
        public float $advanced,
        public float $spent,
        public float $remaining,
        public float $utilization,
    ) {
    }
}
