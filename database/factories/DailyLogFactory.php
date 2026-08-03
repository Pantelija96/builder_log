<?php

namespace Database\Factories;

use App\Enums\WorkerRole;
use App\Models\Company;
use App\Models\ConstructionSite;
use App\Models\DailyLog;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyLog>
 */
class DailyLogFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory();

        return [
            'company_id' => $company,
            'construction_site_id' => ConstructionSite::factory()
                ->for($company),
            'site_manager_id' => Worker::factory()
                ->for($company)
                ->state([
                    'role' => WorkerRole::SITE_MANAGER,
                ]),
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'is_locked' => false,
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'is_locked' => true,
        ]);
    }
}
