<?php

namespace Database\Factories;

use App\Models\Listing;
use App\Models\PriceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceSnapshot>
 */
class PriceSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'listing_id' => Listing::factory(),
            'price_cents' => fake()->numberBetween(500, 30000),
            'is_available' => true,
            'scraped_at' => now(),
        ];
    }
}
