<?php

namespace App\Services;

use App\DTOs\ParsedGameReference;
use Carbon\Carbon;

class DigitalFoundryParser
{
    /**
     * Parse the HTML of a Digital Foundry games browse page into an array of ParsedGameReference DTOs.
     *
     * @return array<ParsedGameReference>
     */
    public function parseGameTable(string $html, string $platform): array
    {
        $games = [];

        preg_match_all(
            '/<tr[^>]*class="[^"]*game[^"]*"[^>]*>(.*?)<\/tr>/s',
            $html,
            $rowMatches,
        );

        foreach ($rowMatches[1] as $row) {
            $parsed = $this->parseRow($row, $platform);

            if ($parsed !== null) {
                $games[] = $parsed;
            }
        }

        return $games;
    }

    private function parseRow(string $rowHtml, string $platform): ?ParsedGameReference
    {
        // Extract title and URL from the game link
        if (! preg_match('/<a[^>]*href="(\/games\/[^"]+)"[^>]*>([^<]+)<\/a>/', $rowHtml, $titleMatch)) {
            return null;
        }

        $title = trim(html_entity_decode($titleMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $sourceUrl = 'https://www.digitalfoundry.net'.$titleMatch[1];

        // Extract all td contents
        preg_match_all('/<td[^>]*>(.*?)<\/td>/s', $rowHtml, $tdMatches);
        $tds = $tdMatches[1] ?? [];

        // Column [1] contains title + publisher/developer
        $publisher = null;
        $developer = null;

        if (isset($tds[1])) {
            $parts = $this->extractPublisherDeveloper($tds[1]);
            $publisher = $parts['publisher'];
            $developer = $parts['developer'];
        }

        // Column [3] contains release dates
        $releaseDatesRaw = [];
        $releaseDate = null;

        if (isset($tds[3])) {
            $rawDateText = strip_tags($tds[3]);
            $releaseDatesRaw = $this->parseReleaseDates($rawDateText);
            $releaseDate = $this->earliestDate($releaseDatesRaw);
        }

        return new ParsedGameReference(
            title: $title,
            publisher: $publisher,
            developer: $developer,
            platform: $platform,
            releaseDate: $releaseDate,
            releaseDatesRaw: $releaseDatesRaw,
            sourceUrl: $sourceUrl,
        );
    }

    /**
     * @return array{publisher: ?string, developer: ?string}
     */
    private function extractPublisherDeveloper(string $tdHtml): array
    {
        $publisher = null;
        $developer = null;

        // Publisher/developer live inside <span class="description">...</span>
        if (preg_match('/<span[^>]*class="description"[^>]*>(.*?)<\/span>/s', $tdHtml, $descMatch)) {
            $descText = strip_tags($descMatch[1]);
            $parts = preg_split('/\s*\/\s*/', $descText);
            $parts = array_map('trim', $parts);
            $parts = array_values(array_filter($parts));

            $publisher = $parts[0] ?? null;
            $developer = $parts[1] ?? null;
        }

        return ['publisher' => $publisher, 'developer' => $developer];
    }

    /**
     * Parse a raw date string like "15th Sep 2009 (NA)16th Sep 2009 (UK/EU)17th Sep 2009 (JPN)"
     * into structured regional date entries.
     *
     * @return array<array{date: string, region: string}>
     */
    public function parseReleaseDates(string $raw): array
    {
        $dates = [];

        preg_match_all(
            '/(\d{1,2})(?:st|nd|rd|th)?\s+(\w+)\s+(\d{4})\s*\(([^)]+)\)/',
            $raw,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $dateStr = $match[1].' '.$match[2].' '.$match[3];

            try {
                $parsed = Carbon::createFromFormat('j F Y', $dateStr);

                if ($parsed !== null) {
                    $dates[] = [
                        'date' => $parsed->toDateString(),
                        'region' => trim($match[4]),
                    ];
                }
            } catch (\Throwable) {
                // Skip unparseable dates
            }
        }

        return $dates;
    }

    /**
     * Return the earliest date from a set of regional release dates.
     *
     * @param  array<array{date: string, region: string}>  $dates
     */
    public function earliestDate(array $dates): ?Carbon
    {
        if (empty($dates)) {
            return null;
        }

        $earliest = null;

        foreach ($dates as $entry) {
            try {
                $date = Carbon::parse($entry['date']);

                if ($earliest === null || $date->lt($earliest)) {
                    $earliest = $date;
                }
            } catch (\Throwable) {
                // Skip
            }
        }

        return $earliest;
    }
}
