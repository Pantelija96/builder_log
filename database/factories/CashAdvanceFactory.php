<?php

namespace Database\Factories;

use App\Models\CashAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAdvance>
 */
class CashAdvanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(
                2,
                1000,
                100000
            ),
            'date' => fake()->dateTimeBetween(
                '-30 days',
                'now'
            ),
        ];
    }
}
