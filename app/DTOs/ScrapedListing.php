<?php

namespace App\DTOs;

use App\Enums\GameCondition;

readonly class ScrapedListing
{
    public function __construct(
        public string $externalId,
        public string $title,
        public int $priceCents,
        public GameCondition $condition,
        public string $listingUrl,
        public ?string $sellerName = null,
        public ?string $imageUrl = null,
        public ?array $rawData = null,
    ) {}
}
