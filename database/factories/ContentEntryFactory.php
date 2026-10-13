<?php

namespace Database\Factories;

use App\Models\ContentEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentEntry>
 */
class ContentEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(3, true),
        ];
    }
}
