<?php

namespace App\Services\Scrapers;

use App\Contracts\MarketplaceScraper;
use App\DTOs\ScrapedListing;
use App\DTOs\ScrapeResult;
use App\Services\ConditionClassifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\DomCrawler\Crawler;

class MercadoLivreScraper implements MarketplaceScraper
{
    private const BASE_URL = 'https://lista.mercadolivre.com.br';

    private const ITEMS_PER_PAGE = 48;

    private const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36',
    ];

    public function __construct(
        private ConditionClassifier $conditionClassifier,
    ) {}

    public function marketplace(): string
    {
        return 'mercadolivre';
    }

    public function scrape(string $searchTerm, int $page = 1): ScrapeResult
    {
        $url = $this->buildSearchUrl($searchTerm, $page);

        $html = $this->fetchWithRateLimit($url);

        if ($html === null) {
            return new ScrapeResult(listings: [], hasMorePages: false, totalFound: 0);
        }

        try {
            return $this->parseSearchPage($html);
        } catch (\Throwable $e) {
            Log::error('MercadoLivre: Failed to parse search page', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new ScrapeResult(listings: [], hasMorePages: false, totalFound: 0);
        }
    }

    private function buildSearchUrl(string $searchTerm, int $page): string
    {
        $slug = str_replace(' ', '-', trim($searchTerm));
        $url = self::BASE_URL . '/' . $slug;

        if ($page > 1) {
            $offset = (($page - 1) * self::ITEMS_PER_PAGE) + 1;
            $url .= '_Desde_' . $offset;
        }

        return $url;
    }

    private function parseSearchPage(string $html): ScrapeResult
    {
        $crawler = new Crawler($html);
        $listings = [];

        $crawler->filter('li.ui-search-layout__item')->each(function (Crawler $item) use (&$listings) {
            try {
                $listing = $this->parseListingItem($item);

                if ($listing !== null) {
                    $listings[] = $listing;
                }
            } catch (\Throwable $e) {
                Log::debug('MercadoLivre: Failed to parse item', ['error' => $e->getMessage()]);
            }
        });

        $hasMorePages = $this->detectNextPage($crawler);

        $totalFound = $this->extractTotalResults($html);

        return new ScrapeResult(
            listings: $listings,
            hasMorePages: $hasMorePages,
            totalFound: $totalFound,
        );
    }

    private function parseListingItem(Crawler $item): ?ScrapedListing
    {
        // Extract title
        $titleNode = $item->filter('a.poly-component__title');

        if ($titleNode->count() === 0) {
            return null;
        }

        $title = trim($titleNode->text());
        $href = $titleNode->attr('href') ?? '';

        // Skip ad/tracking links (click1.mercadolivre)
        if (str_contains($href, 'click1.mercadolivre')) {
            return null;
        }

        // Extract product ID from URL (MLB or MLBxxxxxxx pattern)
        $externalId = $this->extractProductId($href);

        if ($externalId === null) {
            return null;
        }

        // Extract price from aria-label
        $priceCents = $this->extractPrice($item);

        // Skip items with no valid price
        if ($priceCents === 0) {
            return null;
        }

        // Extract image URL
        $imageUrl = $this->extractImage($item);

        // Extract condition
        $condition = $this->extractCondition($item, $title);

        // Clean the URL (remove tracking params after #)
        $cleanUrl = preg_replace('/#.*$/', '', $href) ?? $href;

        return new ScrapedListing(
            externalId: $externalId,
            title: $title,
            priceCents: $priceCents,
            condition: $condition,
            listingUrl: $cleanUrl,
            imageUrl: $imageUrl,
            rawData: [
                'source' => 'search',
                'original_url' => $href,
            ],
        );
    }

    private function extractProductId(string $url): ?string
    {
        if (preg_match('/MLB-?(\d+)/', $url, $match)) {
            return 'MLB' . $match[1];
        }

        return null;
    }

    private function extractPrice(Crawler $item): int
    {
        // Try aria-label approach first (most reliable)
        $priceNode = $item->filter('.poly-price__current .andes-money-amount');

        if ($priceNode->count() > 0) {
            $ariaLabel = $priceNode->first()->attr('aria-label') ?? '';

            if (preg_match('/([\d.]+)\s*reais/', $ariaLabel, $match)) {
                $reais = (int) str_replace('.', '', $match[1]);
                $centavos = 0;

                if (preg_match('/(\d+)\s*centavos/', $ariaLabel, $cMatch)) {
                    $centavos = (int) $cMatch[1];
                }

                return ($reais * 100) + $centavos;
            }
        }

        // Fallback: extract from fraction/cents elements
        $fractionNode = $item->filter('.poly-price__current .andes-money-amount__fraction');

        if ($fractionNode->count() > 0) {
            $fraction = (int) str_replace('.', '', $fractionNode->first()->text());
            $cents = 0;

            $centsNode = $item->filter('.poly-price__current .andes-money-amount__cents');

            if ($centsNode->count() > 0) {
                $cents = (int) $centsNode->first()->text();
            }

            return ($fraction * 100) + $cents;
        }

        return 0;
    }

    private function extractImage(Crawler $item): ?string
    {
        $imgNode = $item->filter('.poly-card__portada img');

        if ($imgNode->count() > 0) {
            return $imgNode->first()->attr('data-src')
                ?? $imgNode->first()->attr('src');
        }

        return null;
    }

    private function extractCondition(Crawler $item, string $title): \App\Enums\GameCondition
    {
        // Check the dedicated condition element
        $conditionNode = $item->filter('.poly-component__item-condition');

        if ($conditionNode->count() > 0) {
            $conditionText = trim($conditionNode->text());

            return $this->conditionClassifier->classify($conditionText);
        }

        // Fall back to title-based classification
        return $this->conditionClassifier->classify($title);
    }

    private function detectNextPage(Crawler $crawler): bool
    {
        // Check for pagination "next" arrow link
        $nextLink = $crawler->filter('a.andes-pagination__link[title="Seguinte"]');

        if ($nextLink->count() > 0) {
            return true;
        }

        // Alternative: check for any _Desde_ links in pagination
        $paginationLinks = $crawler->filter('.andes-pagination a');

        return $paginationLinks->count() > 0;
    }

    private function extractTotalResults(string $html): int
    {
        if (preg_match('/([\d.]+)\s+resultado/', $html, $match)) {
            return (int) str_replace('.', '', $match[1]);
        }

        return 0;
    }

    private function fetchWithRateLimit(string $url): ?string
    {
        $executed = RateLimiter::attempt(
            key: 'scraper-mercadolivre',
            maxAttempts: 5,
            callback: fn () => true,
        );

        if (! $executed) {
            Log::info('MercadoLivre: Rate limited, skipping', ['url' => $url]);

            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate',
                'Connection' => 'keep-alive',
                'Cache-Control' => 'no-cache',
            ])
                ->timeout(15)
                ->retry(3, 2000)
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('MercadoLivre: HTTP error', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MercadoLivre: Request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
