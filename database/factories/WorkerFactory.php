<?php

namespace Database\Factories;

use App\Enums\WorkerRole;
use App\Models\Company;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worker>
 */
class WorkerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'role' => WorkerRole::WORKER,
            'username' => fake()->unique()->userName(),
            'password' => 'password',
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => WorkerRole::ADMIN,
        ]);
    }

    public function siteManager(): static
    {
        return $this->state(fn () => [
            'role' => WorkerRole::SITE_MANAGER,
        ]);
    }

    public function operator(): static
    {
        return $this->state(fn () => [
            'role' => WorkerRole::OPERATOR,
        ]);
    }
}
