<?php

namespace App\Actions\SiteManager;

use App\DTO\SiteManager\GetSiteManagerOverallData;
use App\Models\Expense;
use App\Models\WorkerAttendance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GetSiteManagerOverallAction
{
    public function execute(int $siteManagerId, GetSiteManagerOverallData $data,): Collection
    {
        $expenses = Expense::query()
            ->where('site_manager_id', $siteManagerId)
            ->when(
                $data->dateCreatedFrom,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate('date', '>=', $date)
            )
            ->when(
                $data->dateCreatedTo,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate('date', '<=', $date)
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

        $workerAttendances = WorkerAttendance::query()
            ->where('site_manager_id', $siteManagerId)
            ->when(
                $data->dateCreatedFrom,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate('date', '>=', $date)
            )
            ->when(
                $data->dateCreatedTo,
                fn (Builder $query, Carbon $date) =>
                $query->whereDate('date', '<=', $date)
            )
            ->with([
                'worker',
                'siteManager',
                'constructionSite',
                'dailyLog',
                'creator',
            ])
            ->orderBy('date')
            ->get();

        /*
         * Skupljamo sve construction_site_id-jeve koji postoje
         * u bilo kom od dva izvora.
         */
        $siteIds = $expenses
            ->pluck('construction_site_id')
            ->merge($workerAttendances->pluck('construction_site_id'))
            ->filter()
            ->unique()
            ->values();

        /*
         * Grupisanje po construction_site_id.
         */
        return $siteIds->map(function (int $siteId) use ($expenses, $workerAttendances,)
        {
            return [
                'construction_site_id' => $siteId,

                'construction_site' => $expenses
                        ->firstWhere('construction_site_id', $siteId)
                        ?->constructionSite
                    ?? $workerAttendances
                        ->firstWhere('construction_site_id', $siteId)
                        ?->constructionSite,

                'expenses' => $expenses
                    ->where('construction_site_id', $siteId)
                    ->values(),

                'worker_attendance' => $workerAttendances
                    ->where('construction_site_id', $siteId)
                    ->values(),
            ];
        });
    }
}
