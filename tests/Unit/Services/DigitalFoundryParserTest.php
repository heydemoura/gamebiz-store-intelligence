<?php

namespace Tests\Unit\Services;

use App\Services\DigitalFoundryParser;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class DigitalFoundryParserTest extends TestCase
{
    private DigitalFoundryParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new DigitalFoundryParser;
    }

    public function test_parses_game_table_from_fixture(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/digitalfoundry_ps3_page1.html');

        $games = $this->parser->parseGameTable($html, 'ps3');

        $this->assertCount(60, $games);
    }

    public function test_extracts_game_titles(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/digitalfoundry_ps3_page1.html');

        $games = $this->parser->parseGameTable($html, 'ps3');

        $titles = array_map(fn ($g) => $g->title, $games);

        $this->assertContains('.detuned', $titles);
        $this->assertContains('007 Legends', $titles);
        $this->assertContains('3D Dot Game Heroes', $titles);
    }

    public function test_extracts_publisher_and_developer(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/digitalfoundry_ps3_page1.html');

        $games = $this->parser->parseGameTable($html, 'ps3');

        $detuned = $games[0];
        $this->assertSame('.detuned', $detuned->title);
        $this->assertSame('Sony Interactive Entertainment', $detuned->publisher);
        $this->assertSame('.theprodukkt', $detuned->developer);
    }

    public function test_extracts_release_dates(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/digitalfoundry_ps3_page1.html');

        $games = $this->parser->parseGameTable($html, 'ps3');

        $detuned = $games[0];
        $this->assertNotEmpty($detuned->releaseDatesRaw);
        $this->assertNotNull($detuned->releaseDate);

        // .detuned released 15th Sep 2009 (NA), earliest date
        $this->assertSame('2009-09-15', $detuned->releaseDate->toDateString());
    }

    public function test_extracts_source_url(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/digitalfoundry_ps3_page1.html');

        $games = $this->parser->parseGameTable($html, 'ps3');

        $this->assertStringContainsString('digitalfoundry.net/games/ps3/', $games[0]->sourceUrl);
    }

    public function test_sets_platform_from_argument(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/digitalfoundry_ps3_page1.html');

        $games = $this->parser->parseGameTable($html, 'ps3');

        foreach ($games as $game) {
            $this->assertSame('ps3', $game->platform);
        }
    }

    public function test_parse_release_dates_multiple_regions(): void
    {
        $raw = '15th Sep 2009 (NA)16th Sep 2009 (UK/EU)17th Sep 2009 (JPN)';

        $dates = $this->parser->parseReleaseDates($raw);

        $this->assertCount(3, $dates);
        $this->assertSame('2009-09-15', $dates[0]['date']);
        $this->assertSame('NA', $dates[0]['region']);
        $this->assertSame('2009-09-16', $dates[1]['date']);
        $this->assertSame('UK/EU', $dates[1]['region']);
        $this->assertSame('2009-09-17', $dates[2]['date']);
        $this->assertSame('JPN', $dates[2]['region']);
    }

    public function test_parse_release_dates_empty_string(): void
    {
        $this->assertSame([], $this->parser->parseReleaseDates(''));
        $this->assertSame([], $this->parser->parseReleaseDates('-'));
    }

    public function test_earliest_date_picks_correctly(): void
    {
        $dates = [
            ['date' => '2009-09-17', 'region' => 'JPN'],
            ['date' => '2009-09-15', 'region' => 'NA'],
            ['date' => '2009-09-16', 'region' => 'UK/EU'],
        ];

        $earliest = $this->parser->earliestDate($dates);

        $this->assertInstanceOf(Carbon::class, $earliest);
        $this->assertSame('2009-09-15', $earliest->toDateString());
    }

    public function test_earliest_date_returns_null_for_empty(): void
    {
        $this->assertNull($this->parser->earliestDate([]));
    }

    public function test_returns_empty_for_invalid_html(): void
    {
        $games = $this->parser->parseGameTable('<html><body>Nothing here</body></html>', 'ps3');

        $this->assertSame([], $games);
    }
}
