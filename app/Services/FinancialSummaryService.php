<?php

namespace App\Services;

use App\DTO\Financial\FinancialSummaryData;
use App\Models\Worker;
use Illuminate\Support\Collection;

class FinancialSummaryService
{
    public function summaryForSiteManager(
        Worker $siteManager,
    ): FinancialSummaryData {

        return $this->calculateSummary(
            $siteManager
        );
    }

    public function summaryForAllSiteManagers(Worker $actor): Collection
    {
        return Worker::query()
            ->where('company_id', $actor->company_id)
            ->siteManagers()
            ->get()
            ->map(fn (Worker $worker) => $this->calculateSummary(
                $worker
            )

            );
    }

    private function calculateSummary(
        Worker $siteManager,
    ): FinancialSummaryData {

        $advanced = $siteManager
            ->cashAdvances()
            ->where('company_id', $siteManager->company_id)
            ->sum('amount');

        $spent = $siteManager
            ->expenses()
            ->where('company_id', $siteManager->company_id)
            ->sum('amount');

        $remaining = $advanced - $spent;

        $utilization = $advanced == 0
            ? 0
            : round(
                ($spent / $advanced) * 100,
                2,
            );

        return new FinancialSummaryData(
            siteManager: $siteManager,
            advanced: $advanced,
            spent: $spent,
            remaining: $remaining,
            utilization: $utilization,
        );
    }

    public function cashAdvancesForSiteManager(
        Worker $siteManager,
    ): Collection {
        return $siteManager
            ->cashAdvances()
            ->where('company_id', $siteManager->company_id)
            ->with('creator')
            ->latest('date')
            ->get();
    }
}
