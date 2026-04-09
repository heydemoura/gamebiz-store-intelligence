<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\SearchTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SearchTerm>
 */
class SearchTermFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'term' => fake()->words(2, true),
            'platform' => fake()->optional()->randomElement(Platform::cases()),
            'is_category' => false,
            'is_active' => true,
        ];
    }

    public function category(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_category' => true,
        ]);
    }
}
