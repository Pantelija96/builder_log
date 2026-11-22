<?php

namespace App\Services;

use App\Models\ConstructionSite;
use App\Models\ExcavatorLog;
use App\Models\Expense;
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
            ->where('company_id', $constructionSite->company_id)
            ->where('construction_site_id', $constructionSite->id)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->sum('amount');

        $workerAdvances = WorkerAttendance::query()
            ->where('company_id', $constructionSite->company_id)
            ->where('construction_site_id', $constructionSite->id)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->sum('advance_payment');

        return (float) $expenses + (float) $workerAdvances;
    }

    private function getTotalWorkerHours(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): float {
        $minutes = WorkerAttendance::query()
            ->where('company_id', $constructionSite->company_id)
            ->where('construction_site_id', $constructionSite->id)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->sum(DB::raw($this->durationInMinutes()));

        return round($minutes / 60, 2);
    }

    private function getTotalSubcontractorHours(
        ConstructionSite $constructionSite,
        Carbon $dateFrom,
        Carbon $dateTo,
    ): float {
        $minutes = SubcontractorLog::query()
            ->where('company_id', $constructionSite->company_id)
            ->where('construction_site_id', $constructionSite->id)
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->whereNotNull('started_at')
            ->whereNotNull('finished_at')
            ->sum(DB::raw($this->durationInMinutes()));

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
                                'company_id',
                                $constructionSite->company_id,
                            )
                            ->where(
                                'construction_site_id',
                                $constructionSite->id,
                            )
                            ->whereDate('date', '>=', $dateFrom)
                            ->whereDate('date', '<=', $dateTo);
                    }
                )
                ->sum('work_hours'),
            2
        );
    }

    private function durationInMinutes(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST((strftime('%s', finished_at) - strftime('%s', started_at)) / 60 AS INTEGER)"
            : 'TIMESTAMPDIFF(MINUTE, started_at, finished_at)';
    }
}
