<?php

namespace App\Services;

use App\DTO\Financial\GetConstructionFinancialSummaryData;
use App\Models\ConstructionSite;
use App\Models\Expense;
use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerPayment;

class ConstructionFinancialSummaryService
{
    public function __construct(
        private readonly WorkerSalaryService $workerSalaryService,
    ) {
    }

    public function get(
        Worker $currentWorker,
        GetConstructionFinancialSummaryData $data,
    ): array {
        $companyId = $currentWorker->company_id;

        $dateFrom = $data->dateFrom->toDateString();
        $dateTo = $data->dateTo->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Global expense summary
        |--------------------------------------------------------------------------
        */

        $expenseSummary = Expense::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COUNT(*) as expenses_count,
                COALESCE(SUM(amount), 0) as expenses,
                COALESCE(AVG(amount), 0) as average_expense,
                COALESCE(MAX(amount), 0) as highest_expense,

                COALESCE(
                    SUM(
                        CASE
                            WHEN site_manager_id IS NULL
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) as admin_expenses,

                COALESCE(
                    SUM(
                        CASE
                            WHEN site_manager_id IS NOT NULL
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) as site_manager_expenses,

                SUM(
                    CASE
                        WHEN site_manager_id IS NULL
                        THEN 1
                        ELSE 0
                    END
                ) as admin_expenses_count,

                SUM(
                    CASE
                        WHEN site_manager_id IS NOT NULL
                        THEN 1
                        ELSE 0
                    END
                ) as site_manager_expenses_count
            ')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Attendances for requested period
        |--------------------------------------------------------------------------
        */

        $attendances = WorkerAttendance::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get();

        $salarySummary = $this->workerSalaryService
            ->calculateAttendances($attendances);

        /*
        |--------------------------------------------------------------------------
        | Worker payments
        |--------------------------------------------------------------------------
        */

        $paymentSummary = WorkerPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COUNT(*) as payments_count,
                COALESCE(SUM(amount), 0) as payments
            ')
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Expenses by construction site
        |--------------------------------------------------------------------------
        */

        $expensesBySite = Expense::query()
            ->where('expenses.company_id', $companyId)
            ->whereBetween('expenses.date', [$dateFrom, $dateTo])
            ->join(
                'construction_sites',
                'construction_sites.id',
                '=',
                'expenses.construction_site_id'
            )
            ->groupBy(
                'expenses.construction_site_id',
                'construction_sites.name'
            )
            ->selectRaw('
                expenses.construction_site_id,
                construction_sites.name as construction_site_name,

                COUNT(expenses.id) as expenses_count,
                COALESCE(SUM(expenses.amount), 0) as expenses,

                COALESCE(
                    SUM(
                        CASE
                            WHEN expenses.site_manager_id IS NULL
                            THEN expenses.amount
                            ELSE 0
                        END
                    ),
                    0
                ) as admin_expenses,

                COALESCE(
                    SUM(
                        CASE
                            WHEN expenses.site_manager_id IS NOT NULL
                            THEN expenses.amount
                            ELSE 0
                        END
                    ),
                    0
                ) as site_manager_expenses,

                SUM(
                    CASE
                        WHEN expenses.site_manager_id IS NULL
                        THEN 1
                        ELSE 0
                    END
                ) as admin_expenses_count,

                SUM(
                    CASE
                        WHEN expenses.site_manager_id IS NOT NULL
                        THEN 1
                        ELSE 0
                    END
                ) as site_manager_expenses_count
            ')
            ->get()
            ->keyBy('construction_site_id');

        /*
        |--------------------------------------------------------------------------
        | Expenses by site manager
        |--------------------------------------------------------------------------
        */

        $expensesByManager = Expense::query()
            ->where('expenses.company_id', $companyId)
            ->whereBetween('expenses.date', [$dateFrom, $dateTo])
            ->whereNotNull('expenses.site_manager_id')
            ->join(
                'workers',
                'workers.id',
                '=',
                'expenses.site_manager_id'
            )
            ->groupBy(
                'expenses.site_manager_id',
                'workers.first_name',
                'workers.last_name'
            )
            ->selectRaw('
                expenses.site_manager_id,
                workers.first_name,
                workers.last_name,
                COUNT(expenses.id) as expenses_count,
                COALESCE(SUM(expenses.amount), 0) as expenses
            ')
            ->get()
            ->keyBy('site_manager_id');

        /*
        |--------------------------------------------------------------------------
        | Expenses by date
        |--------------------------------------------------------------------------
        */

        $expensesByDate = Expense::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->selectRaw('
                date,
                COUNT(id) as expenses_count,
                COALESCE(SUM(amount), 0) as expenses,

                COALESCE(
                    SUM(
                        CASE
                            WHEN site_manager_id IS NULL
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) as admin_expenses,

                COALESCE(
                    SUM(
                        CASE
                            WHEN site_manager_id IS NOT NULL
                            THEN amount
                            ELSE 0
                        END
                    ),
                    0
                ) as site_manager_expenses
            ')
            ->get()
            ->keyBy(
                fn ($item) => $this->dateKey($item->date)
            );

        /*
        |--------------------------------------------------------------------------
        | Worker payments by date
        |--------------------------------------------------------------------------
        */

        $paymentsByDate = WorkerPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->groupBy('date')
            ->selectRaw('
                date,
                COUNT(id) as payments_count,
                COALESCE(SUM(amount), 0) as payments
            ')
            ->get()
            ->keyBy(
                fn ($item) => $this->dateKey($item->date)
            );

        /*
        |--------------------------------------------------------------------------
        | Attendance grouping
        |--------------------------------------------------------------------------
        */

        $attendancesBySite = $attendances
            ->groupBy('construction_site_id');

        $attendancesByManager = $attendances
            ->whereNotNull('site_manager_id')
            ->groupBy('site_manager_id');

        $attendancesByDate = $attendances
            ->groupBy(
                fn ($attendance) =>
                $attendance->date->toDateString()
            );

        /*
        |--------------------------------------------------------------------------
        | By construction site
        |--------------------------------------------------------------------------
        */

        $siteIds = $expensesBySite
            ->keys()
            ->merge($attendancesBySite->keys())
            ->filter()
            ->unique()
            ->values();

        $siteNames = ConstructionSite::withTrashed()
            ->where('company_id', $companyId)
            ->whereIn('id', $siteIds)
            ->pluck('name', 'id');

        $byConstructionSite = $siteIds
            ->map(function ($siteId) use (
                $expensesBySite,
                $attendancesBySite,
                $siteNames,
            ) {
                $expense = $expensesBySite->get($siteId);

                $siteAttendances = $attendancesBySite
                    ->get($siteId, collect());

                $salary = $this->workerSalaryService
                    ->calculateAttendances($siteAttendances);

                return [
                    'construction_site_id' => (int) $siteId,

                    'construction_site_name' =>
                        $siteNames->get($siteId),

                    'expenses' => [
                        'total' =>
                            (float) ($expense?->expenses ?? 0),

                        'admin' =>
                            (float) ($expense?->admin_expenses ?? 0),

                        'site_managers' =>
                            (float) ($expense?->site_manager_expenses ?? 0),

                        'count' =>
                            (int) ($expense?->expenses_count ?? 0),

                        'admin_count' =>
                            (int) ($expense?->admin_expenses_count ?? 0),

                        'site_managers_count' =>
                            (int) ($expense?->site_manager_expenses_count ?? 0),
                    ],

                    'worker_advances' => [
                        'amount' =>
                            (float) $salary['advances'],

                        'count' =>
                            $siteAttendances
                                ->filter(
                                    fn ($attendance) =>
                                        (float) $attendance->advance_payment > 0
                                )
                                ->count(),
                    ],

                    'worker_salaries' => [
                        'earned' =>
                            (float) $salary['earned'],

                        'worked_hours' =>
                            (float) $salary['worked_hours'],

                        'workers_count' =>
                            $siteAttendances
                                ->pluck('worker_id')
                                ->unique()
                                ->count(),

                        'has_estimated_hours' =>
                            $salary['has_estimated_hours'],

                        'estimated_attendances' =>
                            $salary['estimated_attendances'],

                        'has_missing_hourly_rates' =>
                            $salary['has_missing_hourly_rates'],

                        'missing_hourly_rate_attendances' =>
                            $salary['missing_hourly_rate_attendances'],
                    ],
                ];
            })
            ->sortByDesc(
                fn ($site) => $site['expenses']['total']
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | By site manager
        |--------------------------------------------------------------------------
        */

        $managerIds = $expensesByManager
            ->keys()
            ->merge($attendancesByManager->keys())
            ->filter()
            ->unique()
            ->values();

        $managers = Worker::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $managerIds)
            ->get()
            ->keyBy('id');

        $bySiteManager = $managerIds
            ->map(function ($managerId) use (
                $expensesByManager,
                $attendancesByManager,
                $managers,
            ) {
                $manager = $managers->get($managerId);

                $expense = $expensesByManager
                    ->get($managerId);

                $managerAttendances = $attendancesByManager
                    ->get($managerId, collect());

                $salary = $this->workerSalaryService
                    ->calculateAttendances($managerAttendances);

                return [
                    'site_manager_id' =>
                        (int) $managerId,

                    'first_name' =>
                        $manager?->first_name,

                    'last_name' =>
                        $manager?->last_name,

                    'expenses' => [
                        'site_manager' =>
                            (float) ($expense?->expenses ?? 0),

                        'count' =>
                            (int) ($expense?->expenses_count ?? 0),
                    ],

                    'worker_advances' => [
                        'amount' =>
                            (float) $salary['advances'],

                        'count' =>
                            $managerAttendances
                                ->filter(
                                    fn ($attendance) =>
                                        (float) $attendance->advance_payment > 0
                                )
                                ->count(),
                    ],

                    'worker_salaries' => [
                        'earned' =>
                            (float) $salary['earned'],

                        'worked_hours' =>
                            (float) $salary['worked_hours'],

                        'workers_count' =>
                            $managerAttendances
                                ->pluck('worker_id')
                                ->unique()
                                ->count(),

                        'has_estimated_hours' =>
                            $salary['has_estimated_hours'],

                        'estimated_attendances' =>
                            $salary['estimated_attendances'],

                        'has_missing_hourly_rates' =>
                            $salary['has_missing_hourly_rates'],

                        'missing_hourly_rate_attendances' =>
                            $salary['missing_hourly_rate_attendances'],
                    ],
                ];
            })
            ->sortByDesc(
                fn ($manager) =>
                $manager['expenses']['site_manager']
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | By date
        |--------------------------------------------------------------------------
        */

        $dates = $expensesByDate
            ->keys()
            ->merge($attendancesByDate->keys())
            ->merge($paymentsByDate->keys())
            ->unique()
            ->sort()
            ->values();

        $byDate = $dates
            ->map(function ($date) use (
                $expensesByDate,
                $attendancesByDate,
                $paymentsByDate,
            ) {
                $expense = $expensesByDate->get($date);

                $payment = $paymentsByDate->get($date);

                $dateAttendances = $attendancesByDate
                    ->get($date, collect());

                $salary = $this->workerSalaryService
                    ->calculateAttendances($dateAttendances);

                return [
                    'date' => $date,

                    'expenses' => [
                        'total' =>
                            (float) ($expense?->expenses ?? 0),

                        'admin' =>
                            (float) ($expense?->admin_expenses ?? 0),

                        'site_managers' =>
                            (float) ($expense?->site_manager_expenses ?? 0),
                    ],

                    'worker_advances' => [
                        'amount' =>
                            (float) $salary['advances'],
                    ],

                    'worker_salaries' => [
                        'earned' =>
                            (float) $salary['earned'],

                        'worked_hours' =>
                            (float) $salary['worked_hours'],
                    ],

                    'worker_payments' => [
                        'amount' =>
                            (float) ($payment?->payments ?? 0),

                        'count' =>
                            (int) ($payment?->payments_count ?? 0),
                    ],
                ];
            })
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Final response
        |--------------------------------------------------------------------------
        */

        return [
            'period' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],

            'summary' => [
                'expenses' => [
                    'total' =>
                        (float) ($expenseSummary?->expenses ?? 0),

                    'admin' =>
                        (float) ($expenseSummary?->admin_expenses ?? 0),

                    'site_managers' =>
                        (float) ($expenseSummary?->site_manager_expenses ?? 0),

                    'count' =>
                        (int) ($expenseSummary?->expenses_count ?? 0),

                    'admin_count' =>
                        (int) ($expenseSummary?->admin_expenses_count ?? 0),

                    'site_managers_count' =>
                        (int) ($expenseSummary?->site_manager_expenses_count ?? 0),

                    'average' =>
                        (float) ($expenseSummary?->average_expense ?? 0),

                    'highest' =>
                        (float) ($expenseSummary?->highest_expense ?? 0),
                ],

                'worker_advances' => [
                    'amount' =>
                        (float) $salarySummary['advances'],

                    'count' =>
                        $attendances
                            ->filter(
                                fn ($attendance) =>
                                    (float) $attendance->advance_payment > 0
                            )
                            ->count(),
                ],

                'worker_salaries' => [
                    'earned' =>
                        (float) $salarySummary['earned'],

                    'worked_hours' =>
                        (float) $salarySummary['worked_hours'],

                    'workers_count' =>
                        $attendances
                            ->pluck('worker_id')
                            ->unique()
                            ->count(),

                    'has_estimated_hours' =>
                        $salarySummary['has_estimated_hours'],

                    'estimated_attendances' =>
                        $salarySummary['estimated_attendances'],

                    'has_missing_hourly_rates' =>
                        $salarySummary['has_missing_hourly_rates'],

                    'missing_hourly_rate_attendances' =>
                        $salarySummary[
                        'missing_hourly_rate_attendances'
                        ],
                ],

                'worker_payments' => [
                    'amount' =>
                        (float) ($paymentSummary?->payments ?? 0),

                    'count' =>
                        (int) ($paymentSummary?->payments_count ?? 0),
                ],

                'construction_sites_count' =>
                    $siteIds->count(),
            ],

            'by_construction_site' =>
                $byConstructionSite,

            'by_site_manager' =>
                $bySiteManager,

            'by_date' =>
                $byDate,
        ];
    }

    private function dateKey(mixed $date): string
    {
        if ($date instanceof \Carbon\CarbonInterface) {
            return $date->toDateString();
        }

        return substr((string) $date, 0, 10);
    }
}
