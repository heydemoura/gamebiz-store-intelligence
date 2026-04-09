<?php

namespace Database\Factories;

use App\Enums\GameCondition;
use App\Models\Game;
use App\Models\Listing;
use App\Models\Marketplace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $now = now();

        return [
            'game_id' => Game::factory(),
            'marketplace_id' => Marketplace::factory(),
            'external_id' => fake()->unique()->uuid(),
            'title' => fake()->sentence(4),
            'price_cents' => fake()->numberBetween(500, 30000),
            'condition' => fake()->randomElement(GameCondition::cases()),
            'seller_name' => fake()->optional()->name(),
            'listing_url' => fake()->url(),
            'image_url' => fake()->optional()->imageUrl(),
            'is_available' => true,
            'raw_data' => null,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_available' => false,
        ]);
    }
}
