<?php

namespace Tests\Feature\Jobs;

use App\Contracts\MarketplaceScraper;
use App\DTOs\ScrapedListing;
use App\DTOs\ScrapeResult;
use App\Enums\GameCondition;
use App\Jobs\ScrapeMarketplace;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\PriceSnapshot;
use App\Services\Scrapers\ScraperManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ScrapeMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private Marketplace $marketplace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->marketplace = Marketplace::factory()->create([
            'slug' => 'meugameusado',
            'name' => 'Meu Game Usado',
        ]);
    }

    public function testJobCreatesListingsFromScrapedData(): void
    {
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'prod-123',
                title: 'GTA V PS4',
                priceCents: 8990,
                condition: GameCondition::Good,
                listingUrl: 'https://example.com/gta-v',
                sellerName: 'Seller One',
                imageUrl: 'https://example.com/gta-v.jpg',
            ),
            new ScrapedListing(
                externalId: 'prod-456',
                title: 'Zelda Breath of the Wild Switch',
                priceCents: 15900,
                condition: GameCondition::LikeNew,
                listingUrl: 'https://example.com/zelda',
            ),
        ]);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps4'));

        $this->assertDatabaseCount('listings', 2);
        $this->assertDatabaseHas('listings', [
            'external_id' => 'prod-123',
            'price_cents' => 8990,
            'marketplace_id' => $this->marketplace->id,
        ]);
        $this->assertDatabaseHas('listings', [
            'external_id' => 'prod-456',
            'price_cents' => 15900,
        ]);
    }

    public function testJobCreatesPriceSnapshots(): void
    {
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'prod-789',
                title: 'FIFA 24 PS5',
                priceCents: 12000,
                condition: GameCondition::Fair,
                listingUrl: 'https://example.com/fifa',
            ),
        ]);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps5'));

        $this->assertDatabaseCount('price_snapshots', 1);
        $this->assertDatabaseHas('price_snapshots', [
            'price_cents' => 12000,
            'is_available' => true,
        ]);
    }

    public function testJobUpdatesExistingListings(): void
    {
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'existing-1',
                title: 'God of War PS4',
                priceCents: 5000,
                condition: GameCondition::Good,
                listingUrl: 'https://example.com/gow',
            ),
        ]);

        // First run
        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps4'));

        $this->assertDatabaseCount('listings', 1);

        // Second run with updated price
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'existing-1',
                title: 'God of War PS4',
                priceCents: 4500,
                condition: GameCondition::Good,
                listingUrl: 'https://example.com/gow',
            ),
        ]);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps4'));

        $this->assertDatabaseCount('listings', 1);
        $this->assertDatabaseHas('listings', ['price_cents' => 4500]);
        $this->assertDatabaseCount('price_snapshots', 2);
    }

    public function testJobMarksUnseenListingsAsUnavailable(): void
    {
        // Create an existing available listing
        Listing::factory()->create([
            'marketplace_id' => $this->marketplace->id,
            'external_id' => 'gone-listing',
            'is_available' => true,
        ]);

        // Scrape returns a different listing
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'new-listing',
                title: 'New Game PS4',
                priceCents: 3000,
                condition: GameCondition::Unknown,
                listingUrl: 'https://example.com/new',
            ),
        ]);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps4'));

        $this->assertDatabaseHas('listings', [
            'external_id' => 'gone-listing',
            'is_available' => false,
        ]);
        $this->assertDatabaseHas('listings', [
            'external_id' => 'new-listing',
            'is_available' => true,
        ]);
    }

    public function testJobCreatesGameRecordsFromListings(): void
    {
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'game-1',
                title: 'Spider-Man Miles Morales PS5',
                priceCents: 9900,
                condition: GameCondition::Good,
                listingUrl: 'https://example.com/spiderman',
            ),
        ]);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps5'));

        $this->assertDatabaseCount('games', 1);

        $listing = Listing::where('external_id', 'game-1')->first();
        $this->assertNotNull($listing->game_id);
    }

    public function testJobUpdatesLastScrapedAt(): void
    {
        $this->mockScraper([]);

        $this->assertNull($this->marketplace->last_scraped_at);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps4'));

        $this->marketplace->refresh();
        $this->assertNotNull($this->marketplace->last_scraped_at);
    }

    public function testJobSkipsListingsWithZeroPriceForSnapshots(): void
    {
        $this->mockScraper([
            new ScrapedListing(
                externalId: 'no-price',
                title: 'Game Without Price PS4',
                priceCents: 0,
                condition: GameCondition::Unknown,
                listingUrl: 'https://example.com/no-price',
            ),
        ]);

        dispatch_sync(new ScrapeMarketplace($this->marketplace, 'ps4'));

        $this->assertDatabaseCount('listings', 1);
        $this->assertDatabaseCount('price_snapshots', 0);
    }

    /**
     * @param  array<ScrapedListing>  $listings
     */
    private function mockScraper(array $listings): void
    {
        $scraper = Mockery::mock(MarketplaceScraper::class);
        $scraper->shouldReceive('scrape')
            ->andReturn(new ScrapeResult(
                listings: $listings,
                hasMorePages: false,
                totalFound: count($listings),
            ));
        $scraper->shouldReceive('marketplace')->andReturn('meugameusado');

        $manager = Mockery::mock(ScraperManager::class);
        $manager->shouldReceive('has')->with('meugameusado')->andReturn(true);
        $manager->shouldReceive('for')->with('meugameusado')->andReturn($scraper);

        $this->app->instance(ScraperManager::class, $manager);
    }
}
