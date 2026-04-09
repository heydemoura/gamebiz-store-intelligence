<?php

namespace Tests\Feature\Controllers;

use App\Models\Game;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testDashboardRequiresAuthentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function testDashboardRendersForAuthenticatedUser(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('stats')
                ->has('recentListings')
                ->has('bestDeals')
            );
    }

    public function testDashboardShowsCorrectStats(): void
    {
        $user = User::factory()->create();
        $marketplace = Marketplace::factory()->create();
        $game = Game::factory()->create();

        Listing::factory()->count(3)->create([
            'game_id' => $game->id,
            'marketplace_id' => $marketplace->id,
            'is_available' => true,
            'price_cents' => 10000,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('stats.totalGames', 1)
                ->where('stats.totalListings', 3)
            );
    }
}
