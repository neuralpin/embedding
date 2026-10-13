<?php

namespace Database\Factories;

use App\Models\ContentChunk;
use App\Models\ContentEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContentChunk>
 */
class ContentChunkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'content_entry_id' => ContentEntry::factory(),
            'position' => fake()->unique()->numberBetween(0, 1000),
            'content' => fake()->paragraph(),
        ];
    }
}
