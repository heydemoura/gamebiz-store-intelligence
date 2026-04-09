<?php

namespace App\DTOs;

use Carbon\Carbon;

readonly class ParsedGameReference
{
    /**
     * @param  array<array{date: string, region: string}>  $releaseDatesRaw
     */
    public function __construct(
        public string $title,
        public ?string $publisher,
        public ?string $developer,
        public string $platform,
        public ?Carbon $releaseDate,
        public array $releaseDatesRaw,
        public ?string $sourceUrl,
    ) {}
}
