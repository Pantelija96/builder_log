<?php

namespace App\Services;

use App\Models\ConstructionSite;
use App\Models\Expense;
use App\Models\ExcavatorLog;
use App\Models\SubcontractorLog;
use App\Models\WorkerAttendance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConstructionSiteStatisticsService
{
    public function get(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): array {
        return [
            'total_expenses' => $this->getTotalExpenses(
                constructionSite: $constructionSite,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),

            'total_worker_hours' => $this->getTotalWorkerHours(
                constructionSite: $constructionSite,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),

            'total_subcontractor_hours' => $this->getTotalSubcontractorHours(
                constructionSite: $constructionSite,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),

            'total_machine_hours' => $this->getTotalMachineHours(
                constructionSite: $constructionSite,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),
        ];
    }

    private function getTotalExpenses(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): float {
        $expenses = Expense::query()
            ->where('construction_site_id', $constructionSite->id)
            ->whereBetween('date', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->sum('amount');

        $workerAdvances = WorkerAttendance::query()
            ->where('construction_site_id', $constructionSite->id)
            ->whereBetween('date', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->sum('advance_payment');

        return (float) $expenses + (float) $workerAdvances;
    }

    private function getTotalWorkerHours(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): float {
        $minutes = WorkerAttendance::query()
            ->where('construction_site_id', $constructionSite->id)
            ->whereBetween('date', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->sum(
                DB::raw(
                    'TIMESTAMPDIFF(MINUTE, started_at, finished_at)'
                )
            );

        return round($minutes / 60, 2);
    }

    private function getTotalSubcontractorHours(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): float {
        $minutes = SubcontractorLog::query()
            ->where('construction_site_id', $constructionSite->id)
            ->whereBetween('date', [
                $dateFrom->toDateString(),
                $dateTo->toDateString(),
            ])
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->sum(
                DB::raw(
                    'TIMESTAMPDIFF(MINUTE, started_at, finished_at)'
                )
            );

        return round($minutes / 60, 2);
    }

    private function getTotalMachineHours(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): float {
        return round(
            ExcavatorLog::query()
                ->whereHas(
                    'machineAssignment',
                    function ($query) use (
                        $constructionSite,
                        $dateFrom,
                        $dateTo,
                    ) {
                        $query
                            ->where(
                                'construction_site_id',
                                $constructionSite->id,
                            )
                            ->whereBetween('date', [
                                $dateFrom->toDateString(),
                                $dateTo->toDateString(),
                            ]);
                    }
                )
                ->sum('work_hours'),
            2
        );
    }
}
