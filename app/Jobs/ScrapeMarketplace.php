<?php

namespace App\Jobs;

use App\DTOs\ScrapedListing;
use App\Enums\Platform;
use App\Models\Game;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\PriceSnapshot;
use App\Services\Scrapers\ScraperManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ScrapeMarketplace implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int> */
    public array $backoff = [30, 60, 120];

    public int $maxExceptions = 2;

    public function __construct(
        public Marketplace $marketplace,
        public string $searchTerm,
        public int $maxPages = 3,
    ) {
        $this->queue = 'scrapers';
    }

    public function uniqueId(): string
    {
        return $this->marketplace->slug . '-' . Str::slug($this->searchTerm);
    }

    public function handle(ScraperManager $scraperManager): void
    {
        if (! $scraperManager->has($this->marketplace->slug)) {
            Log::warning('No scraper available for marketplace', ['slug' => $this->marketplace->slug]);

            return;
        }

        $scraper = $scraperManager->for($this->marketplace->slug);
        $seenExternalIds = [];

        for ($page = 1; $page <= $this->maxPages; $page++) {
            $result = $scraper->scrape($this->searchTerm, $page);

            foreach ($result->listings as $scrapedListing) {
                try {
                    $this->processListing($scrapedListing);
                    $seenExternalIds[] = $scrapedListing->externalId;
                } catch (\Throwable $e) {
                    Log::error('Failed to process listing', [
                        'marketplace' => $this->marketplace->slug,
                        'external_id' => $scrapedListing->externalId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            if (! $result->hasMorePages) {
                break;
            }
        }

        // Mark unseen listings as unavailable
        if (! empty($seenExternalIds)) {
            Listing::where('marketplace_id', $this->marketplace->id)
                ->where('is_available', true)
                ->whereNotIn('external_id', $seenExternalIds)
                ->update(['is_available' => false]);
        }

        $this->marketplace->update(['last_scraped_at' => now()]);

        Log::info('Scrape completed', [
            'marketplace' => $this->marketplace->slug,
            'search_term' => $this->searchTerm,
            'listings_found' => count($seenExternalIds),
        ]);
    }

    private function processListing(ScrapedListing $scrapedListing): void
    {
        $now = now();

        $game = $this->matchOrCreateGame($scrapedListing->title);

        $existing = Listing::where('marketplace_id', $this->marketplace->id)
            ->where('external_id', $scrapedListing->externalId)
            ->first();

        $listing = Listing::updateOrCreate(
            [
                'marketplace_id' => $this->marketplace->id,
                'external_id' => $scrapedListing->externalId,
            ],
            [
                'game_id' => $game?->id,
                'title' => $scrapedListing->title,
                'price_cents' => $scrapedListing->priceCents,
                'condition' => $scrapedListing->condition,
                'seller_name' => $scrapedListing->sellerName,
                'listing_url' => $scrapedListing->listingUrl,
                'image_url' => $scrapedListing->imageUrl,
                'is_available' => true,
                'raw_data' => $scrapedListing->rawData,
                'first_seen_at' => $existing?->first_seen_at ?? $now,
                'last_seen_at' => $now,
            ],
        );

        if ($scrapedListing->priceCents > 0) {
            PriceSnapshot::create([
                'listing_id' => $listing->id,
                'price_cents' => $scrapedListing->priceCents,
                'is_available' => true,
                'scraped_at' => $now,
            ]);
        }
    }

    private function matchOrCreateGame(string $title): ?Game
    {
        $normalized = $this->normalizeTitle($title);
        $platform = $this->detectPlatform($title);

        if (empty($normalized) || mb_strlen($normalized) < 2) {
            return null;
        }

        $slug = Str::slug($normalized . '-' . $platform->value);

        if (empty($slug)) {
            return null;
        }

        try {
            return Game::firstOrCreate(
                ['title' => $normalized, 'platform' => $platform],
                ['slug' => $slug, 'is_verified' => false],
            );
        } catch (\Throwable $e) {
            // Handle unique constraint race condition — try to find existing
            return Game::where('title', $normalized)->where('platform', $platform)->first();
        }
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = mb_strtolower($title);

        // Remove platform suffixes (use word boundaries safe for multibyte)
        $platforms = ['playstation 5', 'playstation 4', 'playstation 3', 'ps3', 'ps4', 'ps5', 'xbox 360', 'xbox one', 'xbox series', 'nintendo switch', 'switch', '3ds', 'ds', 'wii u', 'wii', 'pc', 'gbc', 'gba'];
        foreach ($platforms as $platform) {
            $normalized = preg_replace('/(?<=\s|^)' . preg_quote($platform, '/') . '(?=\s|$|-|,|\.|\/)/iu', '', $normalized) ?? $normalized;
        }

        // Remove common noise words
        $noiseWords = [
            'jogo', 'midia fisica', 'mídia física', 'midia digital', 'mídia digital',
            'usado', 'novo', 'lacrado', 'seminovo', 'semi novo', 'semi-novo',
            'original', 'físico', 'fisico', 'nacional', 'standard edition',
            'edição padrão', 'edicao padrao', 'sony', 'microsoft', 'nintendo',
        ];
        foreach ($noiseWords as $word) {
            $normalized = str_ireplace($word, '', $normalized);
        }

        // Remove stray punctuation and extra whitespace
        $normalized = preg_replace('/\s*[-–—]+\s*$/', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s*[-–—]+\s*(?=\s|$)/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return ucwords(trim($normalized));
    }

    private function detectPlatform(string $title): Platform
    {
        $lower = mb_strtolower($title);

        $platformMap = [
            'ps5' => Platform::Ps5,
            'playstation 5' => Platform::Ps5,
            'ps4' => Platform::Ps4,
            'playstation 4' => Platform::Ps4,
            'ps3' => Platform::Ps3,
            'playstation 3' => Platform::Ps3,
            'xbox series' => Platform::XboxSeries,
            'xbox one' => Platform::XboxOne,
            'xbox 360' => Platform::XboxOne,
            'switch' => Platform::Switch,
            'nintendo switch' => Platform::Switch,
            '3ds' => Platform::Ds3,
            'pc' => Platform::Pc,
        ];

        foreach ($platformMap as $keyword => $platform) {
            if (str_contains($lower, $keyword)) {
                return $platform;
            }
        }

        return Platform::Other;
    }
}
