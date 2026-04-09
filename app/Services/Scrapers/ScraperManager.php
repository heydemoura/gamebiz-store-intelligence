<?php

namespace App\Services\Scrapers;

use App\Contracts\MarketplaceScraper;
use InvalidArgumentException;

class ScraperManager
{
    /**
     * @var array<string, class-string<MarketplaceScraper>>
     */
    private array $scrapers = [
        'meugameusado' => MeuGameUsadoScraper::class,
        'mercadolivre' => MercadoLivreScraper::class,
        'facebook' => FacebookMarketplaceScraper::class,
    ];

    public function for(string $slug): MarketplaceScraper
    {
        if (! isset($this->scrapers[$slug])) {
            throw new InvalidArgumentException("No scraper registered for marketplace: {$slug}");
        }

        return app($this->scrapers[$slug]);
    }

    public function has(string $slug): bool
    {
        return isset($this->scrapers[$slug]);
    }

    /**
     * @return array<string>
     */
    public function available(): array
    {
        return array_keys($this->scrapers);
    }
}
