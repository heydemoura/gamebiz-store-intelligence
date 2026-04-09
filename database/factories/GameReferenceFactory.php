<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\GameReference;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GameReference>
 */
class GameReferenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'platform' => fake()->randomElement(Platform::cases()),
            'publisher' => fake()->optional()->company(),
            'developer' => fake()->optional()->company(),
            'release_date' => fake()->optional()->date(),
            'release_dates_raw' => null,
            'source' => 'digitalfoundry',
            'source_url' => null,
        ];
    }
}
