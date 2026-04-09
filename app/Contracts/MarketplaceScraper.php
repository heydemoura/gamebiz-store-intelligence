<?php

namespace App\Contracts;

use App\DTOs\ScrapeResult;

interface MarketplaceScraper
{
    public function scrape(string $searchTerm, int $page = 1): ScrapeResult;

    public function marketplace(): string;
}
