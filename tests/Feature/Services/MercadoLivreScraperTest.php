<?php

namespace Tests\Feature\Services;

use App\DTOs\ScrapedListing;
use App\Services\Scrapers\MercadoLivreScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MercadoLivreScraperTest extends TestCase
{
    private MercadoLivreScraper $scraper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scraper = app(MercadoLivreScraper::class);
    }

    public function testScrapeParsesListingsFromFixture(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        $result = $this->scraper->scrape('jogo-ps4');

        $this->assertNotEmpty($result->listings);
        $this->assertGreaterThan(10, count($result->listings));

        $listing = $result->listings[0];
        $this->assertInstanceOf(ScrapedListing::class, $listing);
        $this->assertNotEmpty($listing->externalId);
        $this->assertNotEmpty($listing->title);
        $this->assertGreaterThan(0, $listing->priceCents);
        $this->assertNotEmpty($listing->listingUrl);
        $this->assertStringContainsString('mercadolivre.com.br', $listing->listingUrl);
    }

    public function testScrapeExtractsProductIds(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        $result = $this->scraper->scrape('jogo-ps4');

        foreach ($result->listings as $listing) {
            $this->assertMatchesRegularExpression('/^MLB\d+$/', $listing->externalId);
        }
    }

    public function testScrapeExtractsPricesCorrectly(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        $result = $this->scraper->scrape('jogo-ps4');

        foreach ($result->listings as $listing) {
            $this->assertGreaterThan(100, $listing->priceCents, "Price should be > R$1.00 for: {$listing->title}");
            $this->assertLessThan(10000000, $listing->priceCents, "Price should be < R$100,000 for: {$listing->title}");
        }
    }

    public function testScrapeFiltersOutAdLinks(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        $result = $this->scraper->scrape('jogo-ps4');

        foreach ($result->listings as $listing) {
            $this->assertStringNotContainsString('click1.mercadolivre', $listing->listingUrl);
        }
    }

    public function testScrapeExtractsImages(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        $result = $this->scraper->scrape('jogo-ps4');

        $withImages = collect($result->listings)->filter(fn ($l) => $l->imageUrl !== null);
        $this->assertGreaterThan(0, $withImages->count());
    }

    public function testScrapeDetectsTotalResults(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        $result = $this->scraper->scrape('jogo-ps4');

        $this->assertGreaterThan(0, $result->totalFound);
    }

    public function testScrapeHandlesEmptyResponse(): void
    {
        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response('', 404),
        ]);

        $result = $this->scraper->scrape('nonexistent-query');

        $this->assertEmpty($result->listings);
        $this->assertFalse($result->hasMorePages);
    }

    public function testScrapePaginatesCorrectly(): void
    {
        $html = file_get_contents(base_path('tests/fixtures/mercadolivre_search.html'));

        Http::fake([
            'lista.mercadolivre.com.br/*' => Http::response($html, 200),
        ]);

        // Page 2 should add _Desde_49 to the URL
        $this->scraper->scrape('jogo-ps4', 2);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '_Desde_49');
        });
    }

    public function testMarketplaceSlug(): void
    {
        $this->assertSame('mercadolivre', $this->scraper->marketplace());
    }
}
