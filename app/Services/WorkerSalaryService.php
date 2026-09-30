<?php

namespace App\Services;

use App\Models\Worker;
use App\Models\WorkerAttendance;
use App\Models\WorkerPayment;
use Illuminate\Database\Eloquent\Collection;

class WorkerSalaryService
{
    private const DEFAULT_WORK_HOURS = 8;

    public function getBalance(Worker $worker, Worker $currentWorker,): array {
        $this->ensureSameCompany(
            worker: $worker,
            currentWorker: $currentWorker,
        );

        $attendances = WorkerAttendance::query()
            ->where('company_id', $currentWorker->company_id)
            ->where('worker_id', $worker->id)
            ->with([
                'worker',
                'creator',
                'constructionSite',
            ])
            ->orderBy('date')
            ->orderBy('started_at')
            ->get();

        $earned = 0.0;
        $advances = 0.0;
        $estimatedAttendances = 0;
        $missingHourlyRateAttendances = 0;

        foreach ($attendances as $attendance) {
            $advance = (float) $attendance->advance_payment;
            $advances += $advance;

            /*
            Worked hours
            */
            $isEstimated = $attendance->finished_at === null;
            if ($isEstimated) {
                $workedHours = self::DEFAULT_WORK_HOURS;
                $estimatedAttendances++;
            } else {
                $workedMinutes = $attendance->started_at->diffInMinutes($attendance->finished_at);
                $workedHours = $workedMinutes / 60;
            }
            /*
            Hourly rate
            */
            $hasHourlyRate = $attendance->hourly_rate !== null;

            if (! $hasHourlyRate) {
                $missingHourlyRateAttendances++;
            }

            /*
            Earnings
            */

            $attendanceEarned = null;
            $balanceEffect = null;

            if ($hasHourlyRate) {
                $attendanceEarned = $workedHours * (float) $attendance->hourly_rate;
                $balanceEffect = $attendanceEarned - $advance;
                $earned += $attendanceEarned;
            }

            /*
            Runtime calculation attributes
            */

            $attendance->setAttribute('calculated_worked_hours', round($workedHours, 2));
            $attendance->setAttribute('is_estimated', $isEstimated);
            $attendance->setAttribute('has_hourly_rate', $hasHourlyRate);
            $attendance->setAttribute('calculated_earned', $attendanceEarned !== null ? round($attendanceEarned, 2) : null);
            $attendance->setAttribute('calculated_balance_effect', $balanceEffect !== null ? round($balanceEffect, 2) : null);
        }

        /*
        Payments
        */

        $payments = (float) WorkerPayment::query()
            ->where('company_id', $currentWorker->company_id)
            ->where('worker_id', $worker->id)
            ->sum('amount');

        /*
        Outstanding
        */

        $outstanding = $earned - $advances - $payments;

        $calculatedOutstanding = max(0, round($outstanding, 2));

        $hasMissingHourlyRates = $missingHourlyRateAttendances > 0;

        $canPay = ! $hasMissingHourlyRates && $calculatedOutstanding > 0;

        return [
            'worker_id' => $worker->id,
            'earned' => round($earned, 2),
            'advances' => round($advances, 2),
            'payments' => round($payments, 2),
            'outstanding' => $calculatedOutstanding,
            'has_estimated_hours' => $estimatedAttendances > 0,
            'estimated_attendances' => $estimatedAttendances,
            'has_missing_hourly_rates' => $hasMissingHourlyRates,
            'missing_hourly_rate_attendances' => $missingHourlyRateAttendances,
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
}
