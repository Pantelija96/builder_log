<?php

namespace Database\Factories;

use App\Enums\ConstructionSiteStatus;
use App\Models\Company;
use App\Models\ConstructionSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConstructionSite>
 */
class ConstructionSiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->streetName() . ' Construction Site',
            'description' => fake()->optional()->paragraph(),
            'address' => fake()->address(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'status' => ConstructionSiteStatus::ACTIVE,
        ];
    }

    public function paused(): static
    {
        return $this->state(fn () => [
            'status' => ConstructionSiteStatus::PAUSED,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn () => [
            'status' => ConstructionSiteStatus::FINISHED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ConstructionSiteStatus::CANCELLED,
        ]);
    }
}
