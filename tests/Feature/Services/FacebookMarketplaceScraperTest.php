<?php

namespace Tests\Feature\Services;

use App\Services\Scrapers\FacebookMarketplaceScraper;
use App\Services\Scrapers\ScraperManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FacebookMarketplaceScraperTest extends TestCase
{
    use RefreshDatabase;
    public function testMarketplaceSlug(): void
    {
        $scraper = app(FacebookMarketplaceScraper::class);

        $this->assertSame('facebook', $scraper->marketplace());
    }

    public function testScraperManagerHasFacebook(): void
    {
        $manager = app(ScraperManager::class);

        $this->assertTrue($manager->has('facebook'));
        $this->assertContains('facebook', $manager->available());
    }

    public function testScrapeReturnsEmptyWithoutChromeDriver(): void
    {
        // Without ChromeDriver running, scrape should fail gracefully
        $scraper = app(FacebookMarketplaceScraper::class);

        $result = $scraper->scrape('playstation 4');

        $this->assertEmpty($result->listings);
        $this->assertFalse($result->hasMorePages);
    }

    public function testFacebookMarketplaceExistsInSeeder(): void
    {
        $this->seed(\Database\Seeders\MarketplaceSeeder::class);

        $this->assertDatabaseHas('marketplaces', [
            'slug' => 'facebook',
            'name' => 'Facebook Marketplace',
        ]);
    }

    public function testConfigHasFacebookSettings(): void
    {
        $this->assertSame('fortaleza', config('services.facebook.marketplace_location'));
        $this->assertSame('http://localhost:9515', config('services.facebook.chromedriver_url'));
    }
}
