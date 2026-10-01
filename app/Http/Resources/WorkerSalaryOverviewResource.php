<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkerSalaryOverviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'worker_id' => $this->resource['worker_id'],
            'first_name' => $this->resource['first_name'],
            'last_name' => $this->resource['last_name'],

            'period' => [
                'earned' => number_format(
                    $this->resource['period']['earned'],
                    2,
                    '.',
                    ''
                ),

                'advances' => number_format(
                    $this->resource['period']['advances'],
                    2,
                    '.',
                    ''
                ),

                'worked_hours' => number_format(
                    $this->resource['period']['worked_hours'],
                    2,
                    '.',
                    ''
                ),

                'has_estimated_hours' =>
                    $this->resource['period']['has_estimated_hours'],

                'estimated_attendances' =>
                    $this->resource['period']['estimated_attendances'],

                'has_missing_hourly_rates' =>
                    $this->resource['period']['has_missing_hourly_rates'],

                'missing_hourly_rate_attendances' =>
                    $this->resource['period']['missing_hourly_rate_attendances'],
            ],

            'total' => [
                'earned' => number_format(
                    $this->resource['total']['earned'],
                    2,
                    '.',
                    ''
                ),

                'advances' => number_format(
                    $this->resource['total']['advances'],
                    2,
                    '.',
                    ''
                ),

                'payments' => number_format(
                    $this->resource['total']['payments'],
                    2,
                    '.',
                    ''
                ),

                'outstanding' => number_format(
                    $this->resource['total']['outstanding'],
                    2,
                    '.',
                    ''
                ),

                'has_estimated_hours' =>
                    $this->resource['total']['has_estimated_hours'],

                'estimated_attendances' =>
                    $this->resource['total']['estimated_attendances'],

                'has_missing_hourly_rates' =>
                    $this->resource['total']['has_missing_hourly_rates'],

                'missing_hourly_rate_attendances' =>
                    $this->resource['total']['missing_hourly_rate_attendances'],

                'can_pay' =>
                    $this->resource['total']['can_pay'],
            ],
        ];
    }
}
