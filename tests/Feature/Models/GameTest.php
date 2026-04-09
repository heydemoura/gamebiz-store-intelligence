<?php

namespace Tests\Feature\Models;

use App\Enums\GameCondition;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\PriceSnapshot;
use App\Models\SearchTerm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    public function testGameCanBeCreatedWithFactory(): void
    {
        $game = Game::factory()->create();

        $this->assertDatabaseHas('games', ['id' => $game->id]);
        $this->assertInstanceOf(Platform::class, $game->platform);
    }

    public function testGameHasManyListings(): void
    {
        $game = Game::factory()->create();
        $marketplace = Marketplace::factory()->create();

        Listing::factory()->count(3)->create([
            'game_id' => $game->id,
            'marketplace_id' => $marketplace->id,
        ]);

        $this->assertCount(3, $game->listings);
    }

    public function testGameVerifiedState(): void
    {
        $game = Game::factory()->verified()->create();

        $this->assertTrue($game->is_verified);
    }

    public function testMarketplaceHasManyListings(): void
    {
        $marketplace = Marketplace::factory()->create();

        Listing::factory()->count(2)->create([
            'marketplace_id' => $marketplace->id,
        ]);

        $this->assertCount(2, $marketplace->listings);
    }

    public function testMarketplaceIsDueWhenNeverScraped(): void
    {
        $marketplace = Marketplace::factory()->create(['last_scraped_at' => null]);

        $this->assertTrue($marketplace->isDue());
    }

    public function testMarketplaceIsNotDueWhenRecentlyScraped(): void
    {
        $marketplace = Marketplace::factory()->create([
            'last_scraped_at' => now(),
            'scrape_interval_minutes' => 60,
        ]);

        $this->assertFalse($marketplace->isDue());
    }

    public function testMarketplaceIsDueWhenIntervalPassed(): void
    {
        $marketplace = Marketplace::factory()->create([
            'last_scraped_at' => now()->subMinutes(61),
            'scrape_interval_minutes' => 60,
        ]);

        $this->assertTrue($marketplace->isDue());
    }

    public function testInactiveMarketplaceIsNeverDue(): void
    {
        $marketplace = Marketplace::factory()->inactive()->create(['last_scraped_at' => null]);

        $this->assertFalse($marketplace->isDue());
    }

    public function testListingBelongsToGameAndMarketplace(): void
    {
        $listing = Listing::factory()->create();

        $this->assertInstanceOf(Game::class, $listing->game);
        $this->assertInstanceOf(Marketplace::class, $listing->marketplace);
    }

    public function testListingHasManyPriceSnapshots(): void
    {
        $listing = Listing::factory()->create();

        PriceSnapshot::factory()->count(5)->create([
            'listing_id' => $listing->id,
        ]);

        $this->assertCount(5, $listing->priceSnapshots);
    }

    public function testListingConditionCastsToEnum(): void
    {
        $listing = Listing::factory()->create(['condition' => GameCondition::LikeNew]);

        $this->assertSame(GameCondition::LikeNew, $listing->condition);
    }

    public function testListingPriceInReais(): void
    {
        $listing = Listing::factory()->create(['price_cents' => 15990]);

        $this->assertSame(159.9, $listing->priceInReais());
    }

    public function testPriceSnapshotBelongsToListing(): void
    {
        $snapshot = PriceSnapshot::factory()->create();

        $this->assertInstanceOf(Listing::class, $snapshot->listing);
    }

    public function testSearchTermCanBeCreatedWithFactory(): void
    {
        $term = SearchTerm::factory()->create();

        $this->assertDatabaseHas('search_terms', ['id' => $term->id]);
    }

    public function testSearchTermCategoryState(): void
    {
        $term = SearchTerm::factory()->category()->create();

        $this->assertTrue($term->is_category);
    }

    public function testMarketplaceSeederCreatesAllMarketplaces(): void
    {
        $this->seed(\Database\Seeders\MarketplaceSeeder::class);

        $this->assertDatabaseCount('marketplaces', 6);
        $this->assertDatabaseHas('marketplaces', ['slug' => 'olx']);
        $this->assertDatabaseHas('marketplaces', ['slug' => 'enjoei']);
        $this->assertDatabaseHas('marketplaces', ['slug' => 'mercadolivre']);
        $this->assertDatabaseHas('marketplaces', ['slug' => 'amazon']);
        $this->assertDatabaseHas('marketplaces', ['slug' => 'meugameusado']);

        $this->assertDatabaseCount('search_terms', 5);
    }
}
