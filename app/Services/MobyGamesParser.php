<?php

namespace App\Services;

use App\DTOs\ParsedGameReference;
use Carbon\Carbon;

class MobyGamesParser
{
    /**
     * Parse a MobyGames platform browse page into game reference DTOs.
     *
     * @return array<ParsedGameReference>
     */
    public function parseGameTable(string $html, string $platform): array
    {
        $games = [];

        $tableMatch = preg_match(
            '/<table[^>]*class="table table-borders[^"]*"[^>]*>(.*?)<\/table>/s',
            $html,
            $match,
        );

        if (! $tableMatch) {
            return [];
        }

        preg_match_all('/<tr>(.*?)<\/tr>/s', $match[1], $rowMatches);

        foreach ($rowMatches[1] as $row) {
            $parsed = $this->parseRow($row, $platform);

            if ($parsed !== null) {
                $games[] = $parsed;
            }
        }

        return $games;
    }

    /**
     * Detect the maximum page number from pagination links.
     */
    public function detectMaxPage(string $html, string $platform, string $letter): int
    {
        $pattern = '/\/platform\/'.preg_quote($platform, '/').'\/title:'.preg_quote($letter, '/').'\/page:(\d+)\//';

        preg_match_all($pattern, $html, $matches);

        if (empty($matches[1])) {
            return 1;
        }

        return max(array_map('intval', $matches[1]));
    }

    private function parseRow(string $rowHtml, string $platform): ?ParsedGameReference
    {
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $rowHtml, $tdMatches);
        $tds = $tdMatches[1] ?? [];

        if (count($tds) < 2) {
            return null;
        }

        // Column 0: Title with link
        $titleLink = preg_match(
            '/<a[^>]*href="(https:\/\/www\.mobygames\.com\/game\/(\d+)\/[^"]*)"[^>]*>([^<]+)<\/a>/',
            $tds[0],
            $titleMatch,
        );

        if (! $titleLink) {
            return null;
        }

        $sourceUrl = $titleMatch[1];
        $title = trim(html_entity_decode($titleMatch[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        // Column 1: Year
        $year = trim(strip_tags($tds[1] ?? ''));
        $releaseDate = null;

        if (preg_match('/^\d{4}$/', $year)) {
            $releaseDate = Carbon::createFromDate((int) $year, 1, 1);
        }

        // Column 2: Genre
        $genre = trim(strip_tags($tds[2] ?? ''));

        return new ParsedGameReference(
            title: $title,
            publisher: null,
            developer: $genre ?: null,
            platform: $platform,
            releaseDate: $releaseDate,
            releaseDatesRaw: $year ? [['date' => $year, 'region' => 'WW']] : [],
            sourceUrl: $sourceUrl,
        );
    }
}
