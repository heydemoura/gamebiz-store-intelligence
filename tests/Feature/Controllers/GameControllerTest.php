<?php

namespace Tests\Feature\Controllers;

use App\Enums\Platform;
use App\Models\Game;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function testGamesIndexRequiresAuth(): void
    {
        $this->get('/games')->assertRedirect('/login');
    }

    public function testGamesIndexRendersWithGames(): void
    {
        Game::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get('/games')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('games/index')
                ->has('games.data', 3)
                ->has('platforms')
                ->has('filters')
            );
    }

    public function testGamesIndexFiltersByPlatform(): void
    {
        Game::factory()->create(['platform' => Platform::Ps4]);
        Game::factory()->create(['platform' => Platform::Ps5]);
        Game::factory()->create(['platform' => Platform::Switch]);

        $this->actingAs($this->user)
            ->get('/games?platform=ps4')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('games.data', 1)
            );
    }

    public function testGamesIndexFiltersBySearch(): void
    {
        Game::factory()->create(['title' => 'Grand Theft Auto V']);
        Game::factory()->create(['title' => 'Zelda Breath of the Wild']);

        $this->actingAs($this->user)
            ->get('/games?search=Zelda')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('games.data', 1)
            );
    }

    public function testGameShowRendersCorrectly(): void
    {
        $game = Game::factory()->create(['platform' => Platform::Ps4]);
        $marketplace = Marketplace::factory()->create();

        Listing::factory()->count(2)->create([
            'game_id' => $game->id,
            'marketplace_id' => $marketplace->id,
            'is_available' => true,
        ]);

        $this->actingAs($this->user)
            ->get("/games/{$game->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('games/show')
                ->has('game')
                ->has('listings', 2)
                ->has('priceStats')
                ->has('priceHistory')
            );
    }

    public function testGamesExportReturnsCsv(): void
    {
        Game::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->get('/games/export')
            ->assertOk()
            ->assertDownload();

        $this->assertStringContainsString('games-export-', $response->headers->get('content-disposition'));
    }
}
