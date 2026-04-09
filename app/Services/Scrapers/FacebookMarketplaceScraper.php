<?php

namespace App\Services\Scrapers;

use App\Contracts\MarketplaceScraper;
use App\DTOs\ScrapedListing;
use App\DTOs\ScrapeResult;
use App\Services\ConditionClassifier;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class FacebookMarketplaceScraper implements MarketplaceScraper
{
    private const BASE_URL = 'https://www.facebook.com/marketplace';

    private const DEFAULT_LOCATION = 'fortaleza';

    private const SCROLL_PAUSE_MS = 2000;

    private const PAGE_LOAD_TIMEOUT_S = 15;

    public function __construct(
        private ConditionClassifier $conditionClassifier,
    ) {}

    public function marketplace(): string
    {
        return 'facebook';
    }

    public function scrape(string $searchTerm, int $page = 1): ScrapeResult
    {
        $executed = RateLimiter::attempt(
            key: 'scraper-facebook',
            maxAttempts: 3,
            callback: fn () => true,
        );

        if (! $executed) {
            Log::info('Facebook: Rate limited, skipping');

            return new ScrapeResult(listings: [], hasMorePages: false, totalFound: 0);
        }

        $location = config('services.facebook.marketplace_location', self::DEFAULT_LOCATION);
        $url = self::BASE_URL . '/' . $location . '/search?query=' . urlencode($searchTerm);

        // For page > 1, we scroll more on the same URL (FB uses infinite scroll)
        $scrollCount = max(1, $page);

        $driver = null;

        try {
            $driver = $this->createDriver();
            $this->loadCookies($driver);

            $driver->get($url);

            $this->waitForListings($driver);

            // Scroll to load more results
            for ($i = 1; $i < $scrollCount; $i++) {
                $driver->executeScript('window.scrollTo(0, document.body.scrollHeight);');
                usleep(self::SCROLL_PAUSE_MS * 1000);
            }

            $listings = $this->extractListings($driver);

            return new ScrapeResult(
                listings: $listings,
                hasMorePages: count($listings) > 0,
                totalFound: count($listings),
            );
        } catch (\Throwable $e) {
            Log::error('Facebook: Scrape failed', [
                'url' => $url,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new ScrapeResult(listings: [], hasMorePages: false, totalFound: 0);
        } finally {
            if ($driver !== null) {
                try {
                    $driver->quit();
                } catch (\Throwable) {
                    // Ignore cleanup errors
                }
            }
        }
    }

    /**
     * @return array<ScrapedListing>
     */
    private function extractListings(RemoteWebDriver $driver): array
    {
        $listings = [];

        // FB Marketplace listing links follow this pattern
        $listingLinks = $driver->findElements(
            WebDriverBy::cssSelector('a[href*="/marketplace/item/"]')
        );

        $seen = [];

        foreach ($listingLinks as $link) {
            try {
                $href = $link->getAttribute('href') ?? '';

                // Extract listing ID from URL
                preg_match('/\/marketplace\/item\/(\d+)/', $href, $idMatch);
                $externalId = $idMatch[1] ?? null;

                if ($externalId === null || isset($seen[$externalId])) {
                    continue;
                }

                $seen[$externalId] = true;

                // Try to get the card container (parent elements)
                $card = $this->findCardContainer($link, $driver);

                $title = $this->extractTitle($card ?? $link);
                $priceCents = $this->extractPrice($card ?? $link);
                $imageUrl = $this->extractImage($card ?? $link);

                if (empty($title)) {
                    continue;
                }

                $cleanUrl = preg_replace('/\?.*$/', '', $href) ?? $href;

                // Ensure full URL (FB returns relative paths from the DOM)
                if (str_starts_with($cleanUrl, '/')) {
                    $cleanUrl = 'https://www.facebook.com' . $cleanUrl;
                }

                $listings[] = new ScrapedListing(
                    externalId: 'FB' . $externalId,
                    title: $title,
                    priceCents: $priceCents,
                    condition: $this->conditionClassifier->classify($title),
                    listingUrl: $cleanUrl,
                    imageUrl: $imageUrl,
                    rawData: ['source' => 'facebook_marketplace', 'location' => config('services.facebook.marketplace_location', self::DEFAULT_LOCATION)],
                );
            } catch (\Throwable $e) {
                Log::debug('Facebook: Failed to parse listing', ['error' => $e->getMessage()]);
            }
        }

        return $listings;
    }

    /**
     * Walk up the DOM to find the card container that holds title/price/image.
     */
    private function findCardContainer(\Facebook\WebDriver\WebDriverElement $link, RemoteWebDriver $driver): ?\Facebook\WebDriver\WebDriverElement
    {
        try {
            // The link itself is usually the card — FB wraps title, price, image inside the <a>
            return $link;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractTitle(\Facebook\WebDriver\WebDriverElement $element): string
    {
        try {
            // FB renders listing title as a span with specific text styling
            $spans = $element->findElements(WebDriverBy::cssSelector('span'));

            foreach ($spans as $span) {
                $text = trim($span->getText());

                // Skip prices (start with R$), locations, and very short text
                if (empty($text) || mb_strlen($text) < 3) {
                    continue;
                }

                if (str_starts_with($text, 'R$') || str_starts_with($text, 'Grátis')) {
                    continue;
                }

                // Skip location-like strings (e.g., "Fortaleza, CE")
                if (preg_match('/^[A-Z][a-záéíóúãõ]+,\s*[A-Z]{2}$/', $text)) {
                    continue;
                }

                return $text;
            }
        } catch (\Throwable) {
            // Fall through
        }

        return '';
    }

    private function extractPrice(\Facebook\WebDriver\WebDriverElement $element): int
    {
        try {
            $spans = $element->findElements(WebDriverBy::cssSelector('span'));

            foreach ($spans as $span) {
                $text = trim($span->getText());

                if (preg_match('/R\$\s*([\d.,]+)/', $text, $match)) {
                    $priceStr = str_replace('.', '', $match[1]);
                    $priceStr = str_replace(',', '.', $priceStr);

                    return (int) round(((float) $priceStr) * 100);
                }
            }
        } catch (\Throwable) {
            // Fall through
        }

        return 0;
    }

    private function extractImage(\Facebook\WebDriver\WebDriverElement $element): ?string
    {
        try {
            $images = $element->findElements(WebDriverBy::cssSelector('img'));

            foreach ($images as $img) {
                $src = $img->getAttribute('src') ?? '';

                if (! empty($src) && ! str_contains($src, 'data:image') && ! str_contains($src, 'emoji')) {
                    return $src;
                }
            }
        } catch (\Throwable) {
            // Fall through
        }

        return null;
    }

    private function waitForListings(RemoteWebDriver $driver): void
    {
        try {
            $wait = new WebDriverWait($driver, self::PAGE_LOAD_TIMEOUT_S);
            $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector('a[href*="/marketplace/item/"]')
                )
            );
        } catch (\Throwable) {
            Log::warning('Facebook: Timed out waiting for listings to load');
        }
    }

    private function createDriver(): RemoteWebDriver
    {
        $driverUrl = config('services.facebook.chromedriver_url', 'http://localhost:9515');

        $options = (new ChromeOptions)->addArguments([
            '--window-size=1920,1080',
            '--disable-gpu',
            '--headless=new',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-blink-features=AutomationControlled',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.6422.112 Safari/537.36',
            '--lang=pt-BR',
        ]);

        // Disable automation flags that FB detects
        $options->setExperimentalOption('excludeSwitches', ['enable-automation']);
        $options->setExperimentalOption('useAutomationExtension', false);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        return RemoteWebDriver::create($driverUrl, $capabilities);
    }

    private function loadCookies(RemoteWebDriver $driver): void
    {
        $cookieFile = storage_path('app/facebook_cookies.json');

        if (! file_exists($cookieFile)) {
            Log::warning('Facebook: No cookies file found. Run "php artisan facebook:login" to authenticate.');

            return;
        }

        $cookies = json_decode(file_get_contents($cookieFile), true);

        if (! is_array($cookies) || empty($cookies)) {
            Log::warning('Facebook: Cookies file is empty or invalid.');

            return;
        }

        // Navigate to Facebook first to set the domain context
        $driver->get('https://www.facebook.com/');
        usleep(1000000); // 1 second

        foreach ($cookies as $cookie) {
            try {
                $driver->manage()->addCookie($cookie);
            } catch (\Throwable $e) {
                Log::debug('Facebook: Failed to add cookie', [
                    'name' => $cookie['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
