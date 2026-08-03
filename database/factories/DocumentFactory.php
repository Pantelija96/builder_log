<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'type' => fake()->randomElement([
                'contract',
                'permit',
                'project',
                'invoice',
                'report',
                'other',
            ]),
            'original_name' => fake()->word() . '.pdf',
            'path' => 'documents/testing/' . fake()->uuid() . '.pdf',
            'mime_type' => 'application/pdf',
            'size' => fake()->numberBetween(
                10_000,
                10_000_000
            ),
        ];
    }
}
