<?php

namespace Tests\Feature\Commands;

use App\Models\GameReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGamesCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureDir = sys_get_temp_dir().'/game_import_test_'.uniqid();
        mkdir($this->fixtureDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->fixtureDir.'/*'));
        rmdir($this->fixtureDir);

        parent::tearDown();
    }

    public function test_imports_from_digitalfoundry_local_files(): void
    {
        copy(
            base_path('tests/fixtures/digitalfoundry_ps3_page1.html'),
            $this->fixtureDir.'/ps3_page_1.html',
        );

        $this->artisan('import:games', [
            'platform' => 'ps3',
            '--source' => 'digitalfoundry',
            '--from-dir' => $this->fixtureDir,
        ])->assertExitCode(0);

        $this->assertSame(60, GameReference::count());
        $this->assertDatabaseHas('game_references', [
            'title' => '007 Legends',
            'platform' => 'ps3',
            'source' => 'digitalfoundry',
        ]);
    }

    public function test_imports_from_mobygames_local_files(): void
    {
        copy(
            base_path('tests/fixtures/mobygames_ps2_A_p1.html'),
            $this->fixtureDir.'/ps2_moby_A_p1.html',
        );

        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--source' => 'mobygames',
            '--from-dir' => $this->fixtureDir,
        ])->assertExitCode(0);

        $this->assertSame(50, GameReference::count());
        $this->assertDatabaseHas('game_references', [
            'title' => 'Aggressive Inline',
            'platform' => 'ps2',
            'source' => 'mobygames',
        ]);
    }

    public function test_import_is_idempotent(): void
    {
        copy(
            base_path('tests/fixtures/mobygames_ps2_A_p1.html'),
            $this->fixtureDir.'/ps2_moby_A_p1.html',
        );

        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--source' => 'mobygames',
            '--from-dir' => $this->fixtureDir,
        ])->assertExitCode(0);

        $this->assertSame(50, GameReference::count());

        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--source' => 'mobygames',
            '--from-dir' => $this->fixtureDir,
        ])->assertExitCode(0);

        $this->assertSame(50, GameReference::count());
    }

    public function test_dry_run_does_not_save(): void
    {
        copy(
            base_path('tests/fixtures/mobygames_ps2_A_p1.html'),
            $this->fixtureDir.'/ps2_moby_A_p1.html',
        );

        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--source' => 'mobygames',
            '--from-dir' => $this->fixtureDir,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, GameReference::count());
    }

    public function test_mobygames_stores_year_and_genre(): void
    {
        copy(
            base_path('tests/fixtures/mobygames_ps2_A_p1.html'),
            $this->fixtureDir.'/ps2_moby_A_p1.html',
        );

        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--source' => 'mobygames',
            '--from-dir' => $this->fixtureDir,
        ])->assertExitCode(0);

        $game = GameReference::where('title', 'Aggressive Inline')->first();

        $this->assertNotNull($game);
        $this->assertSame('2002-01-01', $game->release_date->toDateString());
        $this->assertSame('Sports', $game->developer);
        $this->assertStringContainsString('mobygames.com', $game->source_url);
    }

    public function test_handles_missing_directory(): void
    {
        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--from-dir' => '/nonexistent/path',
        ])->assertExitCode(1);
    }

    public function test_handles_missing_letter_files(): void
    {
        // Only letter A exists, B-Z should be skipped
        copy(
            base_path('tests/fixtures/mobygames_ps2_A_p1.html'),
            $this->fixtureDir.'/ps2_moby_A_p1.html',
        );

        $this->artisan('import:games', [
            'platform' => 'ps2',
            '--source' => 'mobygames',
            '--from-dir' => $this->fixtureDir,
        ])->assertExitCode(0);

        $this->assertSame(50, GameReference::count());
    }
}
