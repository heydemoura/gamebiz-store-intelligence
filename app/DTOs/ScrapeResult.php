<?php

namespace App\DTOs;

readonly class ScrapeResult
{
    /**
     * @param  array<ScrapedListing>  $listings
     */
    public function __construct(
        public array $listings,
        public bool $hasMorePages = false,
        public int $totalFound = 0,
    ) {}
}
