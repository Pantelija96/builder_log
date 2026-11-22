<?php

namespace App\Services;

use App\DTO\ConstructionSite\GetConstructionSiteFinancialSummaryData;
use App\Models\ConstructionSite;
use App\Models\Expense;
use App\Models\Worker;
use App\Models\WorkerAttendance;

class ConstructionSiteFinancialSummaryService
{
    public function get(
        ConstructionSite $constructionSite,
        GetConstructionSiteFinancialSummaryData $data,
        Worker $worker,
    ): array {

        abort_unless($constructionSite->company_id === $worker->company_id, 404);

        $expenses = Expense::query()
            ->where('construction_site_id', $constructionSite->id)
            ->where('company_id', $worker->company_id)
            ->when($worker->isSiteManager(), fn ($query) => $query->whereHas('dailyLog', fn ($logs) => $logs->visibleTo($worker)))
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
                'dailyLog.siteManagers',
                'attachments',
            ])
            ->orderBy('date')
            ->get();

        $cashAdvances = WorkerAttendance::query()
            ->where('company_id', $worker->company_id)
            ->when($worker->isSiteManager(), fn ($query) => $query->whereHas('dailyLog', fn ($logs) => $logs->visibleTo($worker)))
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
