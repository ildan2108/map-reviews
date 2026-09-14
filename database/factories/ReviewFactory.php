<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'external_id' => fake()->unique()->uuid(),
            'author_name' => fake()->name(),
            'rating' => fake()->numberBetween(1, 5),
            'text' => fake()->paragraph(),
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'organization_reply' => fake()->optional()->paragraph(),
            'organization_reply_at' => fake()->optional()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
