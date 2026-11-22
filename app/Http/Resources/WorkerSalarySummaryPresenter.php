<?php

namespace App\Http\Resources;

final class WorkerSalarySummaryPresenter
{
    public static function present(array $summary): array
    {
        $period = $summary['period'];
        $total = $summary['total'];

        return [
            'period' => [
                'earned' => number_format($period['earned'], 2, '.', ''),
                'advances' => number_format($period['advances'], 2, '.', ''),
                'worked_hours' => number_format($period['worked_hours'], 2, '.', ''),
            ],
            'total' => [
                'earned' => number_format($total['earned'], 2, '.', ''),
                'advances' => number_format($total['advances'], 2, '.', ''),
                'payments' => number_format($total['payments'], 2, '.', ''),
                'outstanding' => number_format($total['outstanding'], 2, '.', ''),
            ],
        ];
    }
}
