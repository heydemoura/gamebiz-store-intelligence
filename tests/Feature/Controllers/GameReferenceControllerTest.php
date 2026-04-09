<?php

namespace Tests\Feature\Controllers;

use App\Enums\Platform;
use App\Models\GameReference;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameReferenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_catalog_index_requires_auth(): void
    {
        $this->get('/catalog')->assertRedirect('/login');
    }

    public function test_catalog_index_renders_with_games(): void
    {
        GameReference::factory()->count(3)->create(['platform' => Platform::Ps4]);

        $this->actingAs($this->user)
            ->get('/catalog?platform=ps4')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('catalog/index')
                ->has('games.data', 3)
                ->has('platforms')
                ->has('filters')
            );
    }

    public function test_catalog_index_filters_by_platform(): void
    {
        GameReference::factory()->create(['platform' => Platform::Ps4]);
        GameReference::factory()->create(['platform' => Platform::Ps5]);

        $this->actingAs($this->user)
            ->get('/catalog?platform=ps5')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('games.data', 1)
            );
    }

    public function test_catalog_index_filters_by_search(): void
    {
        GameReference::factory()->create(['platform' => Platform::Ps4, 'title' => 'God of War']);
        GameReference::factory()->create(['platform' => Platform::Ps4, 'title' => 'Spider-Man']);

        $this->actingAs($this->user)
            ->get('/catalog?platform=ps4&search=Spider')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('games.data', 1)
            );
    }

    public function test_catalog_show_renders(): void
    {
        $game = GameReference::factory()->create(['platform' => Platform::Ps4]);

        $this->actingAs($this->user)
            ->get("/catalog/{$game->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('catalog/show')
                ->has('game')
                ->has('listings')
                ->has('priceStats')
                ->has('priceHistory')
            );
    }

    public function test_catalog_show_includes_linked_listings(): void
    {
        $game = GameReference::factory()->create(['platform' => Platform::Ps4]);
        $marketplace = Marketplace::factory()->create();

        Listing::factory()->count(2)->create([
            'game_reference_id' => $game->id,
            'marketplace_id' => $marketplace->id,
            'is_available' => true,
        ]);

        $this->actingAs($this->user)
            ->get("/catalog/{$game->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('listings', 2)
            );
    }

    public function test_listing_can_be_linked_to_game_reference(): void
    {
        $game = GameReference::factory()->create(['platform' => Platform::Ps4]);
        $listing = Listing::factory()->create();

        $this->actingAs($this->user)
            ->patch("/listings/{$listing->id}", [
                'title' => $listing->title,
                'price_cents' => $listing->price_cents,
                'condition' => $listing->condition->value,
                'listing_url' => $listing->listing_url,
                'game_reference_id' => $game->id,
                'is_available' => true,
            ])
            ->assertRedirect();

        $this->assertSame($game->id, $listing->fresh()->game_reference_id);
    }
}
