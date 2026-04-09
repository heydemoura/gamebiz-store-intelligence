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

class MeuGameUsadoScraper implements MarketplaceScraper
{
    private const BASE_URL = 'https://www.meugameusado.com.br';

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
        return 'meugameusado';
    }

    public function scrape(string $searchTerm, int $page = 1): ScrapeResult
    {
        $categoryUrl = $this->resolveCategoryUrl($searchTerm, $page);

        $html = $this->fetchWithRateLimit($categoryUrl);

        if ($html === null) {
            return new ScrapeResult(listings: [], hasMorePages: false, totalFound: 0);
        }

        return $this->parseCategoryPage($html, $categoryUrl);
    }

    /**
     * Scrape product data from an individual product page URL.
     */
    public function scrapeProductPage(string $url): ?ScrapedListing
    {
        $html = $this->fetchWithRateLimit($url);

        if ($html === null) {
            return null;
        }

        return $this->parseProductPage($html, $url);
    }

    /**
     * Fetch product URLs from the sitemap for full catalog scraping.
     *
     * @return array<string>
     */
    public function fetchSitemapUrls(int $sitemapPage = 1): array
    {
        $url = self::BASE_URL . "/sitemap/product-{$sitemapPage}.xml";

        $html = $this->fetchWithRateLimit($url);

        if ($html === null) {
            return [];
        }

        $urls = [];
        preg_match_all('/<loc>(https:\/\/www\.meugameusado\.com\.br\/jogo-[^<]+)<\/loc>/', $html, $matches);

        if (! empty($matches[1])) {
            $urls = $matches[1];
        }

        return $urls;
    }

    private function resolveCategoryUrl(string $searchTerm, int $page): string
    {
        $categoryMap = [
            'ps4' => '/playstation/playstation-4',
            'ps5' => '/playstation/playstation-5',
            'ps3' => '/playstation/playstation-3',
            'xbox one' => '/xbox/xbox-one',
            'xbox series' => '/xbox/xbox-series-x-s',
            'xbox 360' => '/xbox/xbox-360',
            'switch' => '/nintendo',
            '3ds' => '/nintendo/nintendo-3ds-e-2ds',
        ];

        $normalizedTerm = mb_strtolower(trim($searchTerm));
        $path = $categoryMap[$normalizedTerm] ?? '/playstation/playstation-4';
        $url = self::BASE_URL . $path;

        if ($page > 1) {
            $url .= '?pagina=' . $page;
        }

        return $url;
    }

    private function parseCategoryPage(string $html, string $pageUrl): ScrapeResult
    {
        $listings = [];

        // Extract product data from JavaScript arrays ($produtos_um, etc.)
        preg_match_all(
            '/\{\s*id:\s*(\d+),\s*link:\s*"([^"]+)",\s*opcoes:\s*\[([^\]]*)\]\s*\}/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $productId = $match[1];
            $productLink = $match[2];
            $productUrl = self::BASE_URL . $productLink;

            // Extract the game title from the URL slug
            $title = $this->titleFromSlug($productLink);

            // Only include game listings (not consoles or accessories)
            if (! str_starts_with($productLink, '/jogo-') && ! str_starts_with($productLink, '/Jogo-')) {
                continue;
            }

            $listings[] = new ScrapedListing(
                externalId: $productId,
                title: $title,
                priceCents: 0, // Price loaded dynamically; will be enriched from product page
                condition: $this->conditionClassifier->classify($title),
                listingUrl: $productUrl,
                rawData: ['source' => 'category', 'page_url' => $pageUrl],
            );
        }

        $hasMorePages = str_contains($html, 'proxima') || str_contains($html, 'próxima');

        return new ScrapeResult(
            listings: $listings,
            hasMorePages: $hasMorePages,
            totalFound: count($listings),
        );
    }

    private function parseProductPage(string $html, string $url): ?ScrapedListing
    {
        // Extract product ID
        preg_match('/var PRODUTO_ID\s*=\s*[\'"](\d+)[\'"]/', $html, $idMatch);
        $productId = $idMatch[1] ?? null;

        if ($productId === null) {
            Log::warning('MeuGameUsado: Could not extract product ID', ['url' => $url]);

            return null;
        }

        // Extract price
        preg_match('/var produto_preco\s*=\s*([\d.]+)/', $html, $priceMatch);
        $price = isset($priceMatch[1]) ? (int) round(((float) $priceMatch[1]) * 100) : 0;

        // Extract image
        preg_match('/var imagem_grande\s*=\s*"([^"]+)"/', $html, $imageMatch);
        $imageUrl = $imageMatch[1] ?? null;

        // Extract title from the page
        $crawler = new Crawler($html);
        $title = '';

        try {
            $titleNode = $crawler->filter('title');

            if ($titleNode->count() > 0) {
                $title = $titleNode->text();
                $title = preg_replace('/\s*-\s*MeuGameUsado$/', '', $title) ?? $title;
                $title = trim($title);
            }
        } catch (\Exception) {
            $title = $this->titleFromSlug(parse_url($url, PHP_URL_PATH) ?? '');
        }

        // Extract SKU
        preg_match('/var produto_sku\s*=\s*[\'"]([^"\']+)[\'"]/', $html, $skuMatch);

        return new ScrapedListing(
            externalId: $productId,
            title: $title,
            priceCents: $price,
            condition: $this->conditionClassifier->classify($title),
            listingUrl: $url,
            imageUrl: $imageUrl,
            rawData: [
                'source' => 'product_page',
                'sku' => $skuMatch[1] ?? null,
                'original_price' => $priceMatch[1] ?? null,
            ],
        );
    }

    private function titleFromSlug(string $slug): string
    {
        $slug = ltrim($slug, '/');
        // Remove date suffixes like -2020-07-03-08-51-27
        $slug = preg_replace('/-\d{4}-\d{2}-\d{2}-\d{2}-\d{2}-\d{2}$/', '', $slug) ?? $slug;
        // Remove "jogo-" prefix
        $slug = preg_replace('/^(jogo|Jogo)-/', '', $slug) ?? $slug;

        return ucwords(str_replace('-', ' ', $slug));
    }

    private function fetchWithRateLimit(string $url): ?string
    {
        $executed = RateLimiter::attempt(
            key: 'scraper-meugameusado',
            maxAttempts: 15,
            callback: fn () => true,
        );

        if (! $executed) {
            Log::info('MeuGameUsado: Rate limited, skipping', ['url' => $url]);

            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => self::USER_AGENTS[array_rand(self::USER_AGENTS)],
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ])
                ->timeout(10)
                ->retry(3, 1000)
                ->get($url);

            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('MeuGameUsado: HTTP error', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MeuGameUsado: Request failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
