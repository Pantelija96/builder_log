<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerPayment;
use App\DTO\WorkerAttendance\GetWorkerAttendancesData;
use App\QueryFilters\WorkerAttendanceFilter;

class WorkerSalaryService
{
    private const DEFAULT_WORK_HOURS = 8;

    public function getBalance(Worker $worker, Worker $currentWorker,): array {
        $this->ensureSameCompany(
            worker: $worker,
            currentWorker: $currentWorker,
        );

        /*
         * Get all attendances for this worker.
         */
        $attendances = WorkerAttendance::query()
            ->where('company_id', $currentWorker->company_id)
            ->where('worker_id', $worker->id)
            ->with([
                'worker',
                'creator',
                'constructionSite',
                'machineAssignment.machine',
            ])
            ->orderBy('date')
            ->orderBy('started_at')
            ->get();

        /*
         * Calculate attendance-based salary data.
         *
         * This calculates:
         * - earned
         * - advances
         * - worked hours
         * - estimated attendances
         * - missing hourly rates
         *
         * It also sets calculated runtime attributes
         * on each attendance.
         */
        $calculated = $this->calculateAttendances(
            $attendances
        );

        /*
         * Get all salary payments made to this worker.
         */
        $payments = (float) WorkerPayment::query()
            ->where('company_id', $currentWorker->company_id)
            ->where('worker_id', $worker->id)
            ->sum('amount');

        /*
         * Outstanding salary:
         *
         * earned
         * - advances
         * - payments
         */
        $outstanding =
            $calculated['earned']
            - $calculated['advances']
            - $payments;

        /*
         * We never expose a negative outstanding balance.
         */
        $calculatedOutstanding = max(
            0,
            round($outstanding, 2)
        );

        /*
         * Worker can be paid only when:
         *
         * 1. All attendances have an hourly rate.
         * 2. There is something left to pay.
         */
        $canPay =
            ! $calculated['has_missing_hourly_rates']
            && $calculatedOutstanding > 0;

        return [
            'worker_id' => $worker->id,

            'earned' => $calculated['earned'],

            'advances' => $calculated['advances'],

            'payments' => round($payments, 2),

            'outstanding' => $calculatedOutstanding,

            'has_estimated_hours' =>
                $calculated['has_estimated_hours'],

            'estimated_attendances' =>
                $calculated['estimated_attendances'],

            'has_missing_hourly_rates' =>
                $calculated['has_missing_hourly_rates'],

            'missing_hourly_rate_attendances' =>
                $calculated['missing_hourly_rate_attendances'],

            'can_pay' => $canPay,

            'attendances' => $attendances,
        ];
    }

    public function getOutstanding(Worker $worker, Worker $currentWorker,): float {
        $balance = $this->getBalance(
            worker: $worker,
            currentWorker: $currentWorker,
        );

        return (float) $balance['outstanding'];
    }

    public function hasMissingHourlyRates(Worker $worker, Worker $currentWorker,): bool {
        $balance = $this->getBalance(
            worker: $worker,
            currentWorker: $currentWorker,
        );

        return $balance['has_missing_hourly_rates'];
    }

    private function ensureSameCompany(Worker $worker, Worker $currentWorker,): void {
        abort_unless(
            $worker->company_id === $currentWorker->company_id,
            404
        );
    }

    public function getAllBalances(GetWorkerAttendancesData $data, Worker $currentWorker,): array {
        abort_unless(
            $currentWorker->isAdmin(),
            403,
            'Only administrators can view worker salaries.'
        );

        $periodQuery = WorkerAttendance::query()->where('company_id', $currentWorker->company_id);

        $periodQuery = (new WorkerAttendanceFilter($data))->apply($periodQuery);

        $periodAttendances = $periodQuery
            ->with([
                'worker',
                'creator',
                'constructionSite',
                'machineAssignment.machine',
            ])
            ->get();

        $workerIds = $periodAttendances
            ->pluck('worker_id')
            ->unique()
            ->values();

        $workers = Worker::query()
            ->where('company_id', $currentWorker->company_id)
            ->whereIn('id', $workerIds)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        $result = [];

        $periodSummary = [
            'earned' => 0.0,
            'advances' => 0.0,
            'worked_hours' => 0.0,
        ];

        $totalSummary = [
            'earned' => 0.0,
            'advances' => 0.0,
            'payments' => 0.0,
            'outstanding' => 0.0,
        ];

        foreach ($workers as $worker) {
            $workerPeriodAttendances = $periodAttendances
                ->where('worker_id', $worker->id)
                ->values();

            $period = $this->calculateAttendances(
                $workerPeriodAttendances
            );

            $total = $this->getBalance(
                worker: $worker,
                currentWorker: $currentWorker,
            );

            $result[] = [
                'worker_id' => $worker->id,
                'first_name' => $worker->first_name,
                'last_name' => $worker->last_name,

                'period' => [
                    'earned' => $period['earned'],
                    'advances' => $period['advances'],
                    'worked_hours' => $period['worked_hours'],
                    'has_estimated_hours' => $period['has_estimated_hours'],
                    'estimated_attendances' => $period['estimated_attendances'],
                    'has_missing_hourly_rates' => $period['has_missing_hourly_rates'],
                    'missing_hourly_rate_attendances' => $period['missing_hourly_rate_attendances'],
                ],

                'total' => [
                    'earned' => $total['earned'],
                    'advances' => $total['advances'],
                    'payments' => $total['payments'],
                    'outstanding' => $total['outstanding'],
                    'has_estimated_hours' => $total['has_estimated_hours'],
                    'estimated_attendances' => $total['estimated_attendances'],
                    'has_missing_hourly_rates' => $total['has_missing_hourly_rates'],
                    'missing_hourly_rate_attendances' => $total['missing_hourly_rate_attendances'],
                    'can_pay' => $total['can_pay'],
                ],
            ];

            $periodSummary['earned'] += $period['earned'];
            $periodSummary['advances'] += $period['advances'];
            $periodSummary['worked_hours'] += $period['worked_hours'];

            $totalSummary['earned'] += $total['earned'];
            $totalSummary['advances'] += $total['advances'];
            $totalSummary['payments'] += $total['payments'];
            $totalSummary['outstanding'] += $total['outstanding'];
        }

        return [
            'workers' => $result,

            'summary' => [
                'period' => [
                    'earned' => round($periodSummary['earned'], 2),
                    'advances' => round($periodSummary['advances'], 2),
                    'worked_hours' => round($periodSummary['worked_hours'], 2),
                ],

                'total' => [
                    'earned' => round($totalSummary['earned'], 2),
                    'advances' => round($totalSummary['advances'], 2),
                    'payments' => round($totalSummary['payments'], 2),
                    'outstanding' => round($totalSummary['outstanding'], 2),
                ],
            ],
        ];
    }

    private function calculateAttendances(
        iterable $attendances,
    ): array {
        $earned = 0.0;
        $advances = 0.0;
        $workedHoursTotal = 0.0;
        $estimatedAttendances = 0;
        $missingHourlyRateAttendances = 0;

        foreach ($attendances as $attendance) {
            $advance = (float) $attendance->advance_payment;
            $advances += $advance;

            $isEstimated = $attendance->finished_at === null;

            if ($isEstimated) {
                $workedHours = self::DEFAULT_WORK_HOURS;
                $estimatedAttendances++;
            } else {
                $workedMinutes = $attendance->started_at
                    ->diffInMinutes($attendance->finished_at);

                $workedHours = $workedMinutes / 60;
            }

            $workedHoursTotal += $workedHours;

            $hasHourlyRate = $attendance->hourly_rate !== null;

            if (! $hasHourlyRate) {
                $missingHourlyRateAttendances++;
            }

            $attendanceEarned = null;
            $balanceEffect = null;

            if ($hasHourlyRate) {
                $attendanceEarned =
                    $workedHours * (float) $attendance->hourly_rate;

                $balanceEffect =
                    $attendanceEarned - $advance;

                $earned += $attendanceEarned;
            }

            $attendance->setAttribute(
                'calculated_worked_hours',
                round($workedHours, 2)
            );

            $attendance->setAttribute(
                'is_estimated',
                $isEstimated
            );

            $attendance->setAttribute(
                'has_hourly_rate',
                $hasHourlyRate
            );

            $attendance->setAttribute(
                'calculated_earned',
                $attendanceEarned !== null
                    ? round($attendanceEarned, 2)
                    : null
            );

            $attendance->setAttribute(
                'calculated_balance_effect',
                $balanceEffect !== null
                    ? round($balanceEffect, 2)
                    : null
            );
        }

        return [
            'earned' => round($earned, 2),
            'advances' => round($advances, 2),
            'worked_hours' => round($workedHoursTotal, 2),

            'has_estimated_hours' =>
                $estimatedAttendances > 0,

            'estimated_attendances' =>
                $estimatedAttendances,

            'has_missing_hourly_rates' =>
                $missingHourlyRateAttendances > 0,

            'missing_hourly_rate_attendances' =>
                $missingHourlyRateAttendances,
        ];
    }
}
