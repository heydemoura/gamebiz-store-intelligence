<?php

namespace Database\Factories;

use App\Models\Marketplace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Marketplace>
 */
class MarketplaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(2),
            'base_url' => fake()->url(),
            'is_active' => true,
            'scrape_interval_minutes' => 60,
            'rate_limit_per_minute' => 10,
            'last_scraped_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
