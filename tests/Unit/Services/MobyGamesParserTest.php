<?php

namespace Tests\Unit\Services;

use App\Services\MobyGamesParser;
use PHPUnit\Framework\TestCase;

class MobyGamesParserTest extends TestCase
{
    private MobyGamesParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new MobyGamesParser;
    }

    public function test_parses_game_table_from_fixture(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/mobygames_ps2_A_p1.html');

        $games = $this->parser->parseGameTable($html, 'ps2');

        $this->assertCount(50, $games);
    }

    public function test_extracts_game_titles(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/mobygames_ps2_A_p1.html');

        $games = $this->parser->parseGameTable($html, 'ps2');

        $titles = array_map(fn ($g) => $g->title, $games);

        $this->assertContains('Aggressive Inline', $titles);
        $this->assertContains('Ace Combat 5: The Unsung War', $titles);
    }

    public function test_extracts_year_as_release_date(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/mobygames_ps2_A_p1.html');

        $games = $this->parser->parseGameTable($html, 'ps2');

        $aggressive = collect($games)->firstWhere('title', 'Aggressive Inline');

        $this->assertNotNull($aggressive);
        $this->assertNotNull($aggressive->releaseDate);
        $this->assertSame('2002', $aggressive->releaseDate->format('Y'));
    }

    public function test_extracts_genre_as_developer_field(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/mobygames_ps2_A_p1.html');

        $games = $this->parser->parseGameTable($html, 'ps2');

        $aggressive = collect($games)->firstWhere('title', 'Aggressive Inline');

        $this->assertSame('Sports', $aggressive->developer);
    }

    public function test_extracts_source_url(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/mobygames_ps2_A_p1.html');

        $games = $this->parser->parseGameTable($html, 'ps2');

        $this->assertStringContainsString('mobygames.com/game/', $games[0]->sourceUrl);
    }

    public function test_sets_platform(): void
    {
        $html = file_get_contents(__DIR__.'/../../fixtures/mobygames_ps2_A_p1.html');

        $games = $this->parser->parseGameTable($html, 'ps2');

        foreach ($games as $game) {
            $this->assertSame('ps2', $game->platform);
        }
    }

    public function test_returns_empty_for_invalid_html(): void
    {
        $this->assertSame([], $this->parser->parseGameTable('<html></html>', 'ps2'));
    }

    public function test_detect_max_page(): void
    {
        // Simulate HTML with pagination links
        $html = '<a href="/platform/ps2/title:S/page:1/">1</a><a href="/platform/ps2/title:S/page:2/">2</a><a href="/platform/ps2/title:S/page:3/">3</a>';

        $this->assertSame(3, $this->parser->detectMaxPage($html, 'ps2', 'S'));
    }

    public function test_detect_max_page_no_pagination(): void
    {
        $html = '<html>no pagination</html>';

        $this->assertSame(1, $this->parser->detectMaxPage($html, 'ps2', 'A'));
    }
}
