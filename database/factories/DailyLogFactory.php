<?php

namespace Database\Factories;

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
        return [
            'company_id' => Company::factory(),
            'construction_site_id' => fn (array $attributes) => ConstructionSite::factory()->create(['company_id' => $attributes['company_id']])->id,
            'date' => fake()->dateTimeBetween('-30 days', 'now'),
            'is_locked' => false,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (DailyLog $log) {
            if (! $log->siteManagers()->exists()) {
                $log->siteManagers()->attach(Worker::factory()->for($log->company)->siteManager()->create());
            }
        });
    }

    public function withManager(Worker $manager): static
    {
        return $this->hasAttached($manager, [], 'siteManagers');
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
