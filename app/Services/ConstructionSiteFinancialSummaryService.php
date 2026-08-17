<?php

namespace App\Services;

use App\DTO\ConstructionSite\GetConstructionSiteFinancialSummaryData;
use App\Models\ConstructionSite;
use App\Models\Expense;
use App\Models\WorkerAttendance;
use Illuminate\Database\Eloquent\Collection;

class ConstructionSiteFinancialSummaryService
{
    public function get(
        ConstructionSite $constructionSite,
        GetConstructionSiteFinancialSummaryData $data,
    ): array {

        $expenses = Expense::query()
            ->where('construction_site_id', $constructionSite->id)
            ->whereBetween(
                'date',
                [
                    $data->dateFrom,
                    $data->dateTo,
                ]
            )
            ->with([
                'creator',
                'siteManager',
                'constructionSite',
                'dailyLog',
                'attachments',
            ])
            ->orderBy('date')
            ->get();

        $cashAdvances = WorkerAttendance::query()
            ->where(
                'construction_site_id',
                $constructionSite->id,
            )
            ->whereBetween(
                'date',
                [
                    $data->dateFrom,
                    $data->dateTo,
                ]
            )
            ->where('advance_payment', '>', 0)
            ->with([
                'worker',
            ])
            ->orderBy('date')
            ->get();

        return [
            'expenses' => $expenses,
            'cash_advances' => $cashAdvances,
        ];
    }
}
