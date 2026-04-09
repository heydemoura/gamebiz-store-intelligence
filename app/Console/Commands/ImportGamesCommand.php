<?php

namespace App\Console\Commands;

use App\DTOs\ParsedGameReference;
use App\Models\GameReference;
use App\Services\DigitalFoundryParser;
use App\Services\MobyGamesParser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

#[Signature('import:games {platform : Platform slug (e.g. ps2, ps3, ps4, ps5)} {--source=mobygames : Data source (mobygames or digitalfoundry)} {--from-dir= : Import from local HTML files instead of fetching} {--save-to= : Save fetched pages locally for reuse} {--delay=5 : Seconds to wait between page fetches} {--dry-run : Parse and display without saving}')]
#[Description('Import a canonical game reference catalog from MobyGames or Digital Foundry')]
class ImportGamesCommand extends Command
{
    private const MOBYGAMES_URL = 'https://www.mobygames.com/platform';

    private const DIGITALFOUNDRY_URL = 'https://www.digitalfoundry.net/games/browse';

    private const MOBYGAMES_LETTERS = ['09', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];

    /**
     * Maps Platform enum values to MobyGames URL slugs.
     * MobyGames uses full names in URLs while we use short slugs internally.
     */
    private const MOBYGAMES_PLATFORM_MAP = [
        'ps2' => 'ps2',
        'ps3' => 'ps3',
        'ps4' => 'playstation-4',
        'ps5' => 'playstation-5',
        'xbox_one' => 'xbox-one',
        'xbox_series' => 'xbox-series-xs',
        'xbox_360' => 'xbox360',
        'switch' => 'switch',
        'pc' => 'windows',
        '3ds' => '3ds',
    ];

    public function handle(DigitalFoundryParser $dfParser, MobyGamesParser $mobyParser): int
    {
        $platformInput = $this->argument('platform');

        // Resolve platform: accept both enum values (ps4) and MobyGames slugs (playstation-4)
        $reverseMap = array_flip(self::MOBYGAMES_PLATFORM_MAP);
        $platform = $reverseMap[$platformInput] ?? $platformInput;
        $source = $this->option('source');
        $fromDir = $this->option('from-dir');
        $saveTo = $this->option('save-to');
        $delay = (int) $this->option('delay');
        $dryRun = $this->option('dry-run');

        if (! in_array($source, ['mobygames', 'digitalfoundry'])) {
            $this->error("Unknown source: {$source}. Use 'mobygames' or 'digitalfoundry'.");

            return self::FAILURE;
        }

        if ($fromDir && ! is_dir($fromDir)) {
            $this->error("Directory not found: {$fromDir}");

            return self::FAILURE;
        }

        if ($saveTo && ! is_dir($saveTo)) {
            mkdir($saveTo, 0755, true);
        }

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be saved.');
        }

        // Resolve the MobyGames URL slug (may differ from internal platform slug)
        $mobySlug = self::MOBYGAMES_PLATFORM_MAP[$platform] ?? $platform;

        $this->line("  Platform: {$platform} (MobyGames URL slug: {$mobySlug})");

        return $source === 'mobygames'
            ? $this->importFromMobyGames($platform, $mobySlug, $mobyParser, $fromDir, $saveTo, $delay, $dryRun)
            : $this->importFromDigitalFoundry($platform, $dfParser, $fromDir, $saveTo, $delay, $dryRun);
    }

    private function importFromMobyGames(
        string $platform,
        string $mobySlug,
        MobyGamesParser $parser,
        ?string $fromDir,
        ?string $saveTo,
        int $delay,
        bool $dryRun,
    ): int {
        $this->info("Importing {$platform} games from MobyGames (A–Z, {$delay}s delay)...");

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $totalFetched = 0;

        $progressBar = $this->output->createProgressBar(count(self::MOBYGAMES_LETTERS));
        $progressBar->start();

        foreach (self::MOBYGAMES_LETTERS as $letter) {
            $page = 1;

            while (true) {
                $fileKey = "{$mobySlug}_moby_{$letter}_p{$page}";
                $html = $this->loadOrFetch($fileKey, $fromDir, $saveTo, $delay, $totalFetched, function () use ($mobySlug, $letter, $page) {
                    return $this->fetchMobyGamesPage($mobySlug, $letter, $page);
                });

                if ($html === null) {
                    if ($page === 1) {
                        $this->newLine();
                        $this->warn("  Failed to load letter {$letter}, skipping.");
                    }
                    break;
                }

                $games = $parser->parseGameTable($html, $platform);

                if (empty($games)) {
                    break;
                }

                foreach ($games as $game) {
                    if ($dryRun) {
                        $created++;

                        continue;
                    }

                    $result = $this->importGame($game, 'mobygames');

                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }

                $totalFetched++;

                // Check if there are more pages for this letter
                $maxPage = $parser->detectMaxPage($html, $mobySlug, $letter);

                if ($page >= $maxPage) {
                    break;
                }

                $page++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->printSummary($created, $updated, $skipped);

        return self::SUCCESS;
    }

    private function importFromDigitalFoundry(
        string $platform,
        DigitalFoundryParser $parser,
        ?string $fromDir,
        ?string $saveTo,
        int $delay,
        bool $dryRun,
    ): int {
        $pages = 31;
        $this->info("Importing {$platform} games from Digital Foundry ({$pages} pages, {$delay}s delay)...");

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $progressBar = $this->output->createProgressBar($pages);
        $progressBar->start();

        for ($page = 1; $page <= $pages; $page++) {
            $fileKey = "{$platform}_page_{$page}";
            $html = $this->loadOrFetch($fileKey, $fromDir, $saveTo, $delay, $page - 1, function () use ($platform, $page) {
                return $this->fetchDigitalFoundryPage($platform, $page);
            });

            if ($html === null) {
                $this->newLine();
                $this->warn("  Failed to load page {$page}, skipping.");
                $progressBar->advance();

                continue;
            }

            $games = $parser->parseGameTable($html, $platform);

            if (empty($games)) {
                $progressBar->advance();

                continue;
            }

            foreach ($games as $game) {
                if ($dryRun) {
                    $created++;

                    continue;
                }

                $result = $this->importGame($game, 'digitalfoundry');

                if ($result === 'created') {
                    $created++;
                } elseif ($result === 'updated') {
                    $updated++;
                } else {
                    $skipped++;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->printSummary($created, $updated, $skipped);

        return self::SUCCESS;
    }

    private function loadOrFetch(string $fileKey, ?string $fromDir, ?string $saveTo, int $delay, int $fetchIndex, callable $fetcher): ?string
    {
        // Try local file first
        if ($fromDir) {
            return $this->readLocalFile($fromDir, $fileKey);
        }

        if ($saveTo) {
            $cached = $this->readLocalFile($saveTo, $fileKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        // Rate limit
        if ($fetchIndex > 0) {
            sleep($delay);
        }

        $html = $fetcher();

        if ($html !== null && $saveTo) {
            file_put_contents(rtrim($saveTo, '/')."/{$fileKey}.html", $html);
        }

        return $html;
    }

    private function readLocalFile(string $dir, string $fileKey): ?string
    {
        $filePath = rtrim($dir, '/')."/{$fileKey}.html";

        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        return $content !== false && strlen($content) > 500 ? $content : null;
    }

    private function fetchMobyGamesPage(string $platform, string $letter, int $page): ?string
    {
        $url = self::MOBYGAMES_URL."/{$platform}/title:{$letter}/";

        if ($page > 1) {
            $url .= "page:{$page}/";
        }

        return $this->wgetFetch($url);
    }

    private function fetchDigitalFoundryPage(string $platform, int $page): ?string
    {
        $url = self::DIGITALFOUNDRY_URL.'?'.http_build_query([
            'sort' => 'title',
            'system' => $platform,
            'style' => 'table',
            'page' => $page,
        ]);

        return $this->wgetFetch($url);
    }

    private function wgetFetch(string $url): ?string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'game_import_');

        try {
            $result = Process::timeout(30)->run([
                'wget', '-q', '-O', $tempFile,
                '--header=User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.6422.112 Safari/537.36',
                '--header=Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                '--header=Accept-Language: en-US,en;q=0.9',
                $url,
            ]);

            if ($result->successful() && file_exists($tempFile) && filesize($tempFile) > 500) {
                $content = file_get_contents($tempFile);
                @unlink($tempFile);

                return $content !== false ? $content : null;
            }

            @unlink($tempFile);

            return null;
        } catch (\Throwable) {
            @unlink($tempFile);

            return null;
        }
    }

    private function importGame(ParsedGameReference $game, string $source): string
    {
        $slug = Str::slug($game->title.'-'.$game->platform);

        if (empty($slug)) {
            return 'skipped';
        }

        $existing = GameReference::where('title', $game->title)
            ->where('platform', $game->platform)
            ->first();

        $data = [
            'slug' => $slug,
            'publisher' => $game->publisher,
            'developer' => $game->developer,
            'release_date' => $game->releaseDate,
            'release_dates_raw' => $game->releaseDatesRaw,
            'source' => $source,
            'source_url' => $game->sourceUrl,
        ];

        if ($existing) {
            $existing->update($data);

            return 'updated';
        }

        try {
            GameReference::create([
                'title' => $game->title,
                'platform' => $game->platform,
                ...$data,
            ]);

            return 'created';
        } catch (\Throwable) {
            return 'skipped';
        }
    }

    private function printSummary(int $created, int $updated, int $skipped): void
    {
        $this->newLine(2);
        $this->info('Import complete:');
        $this->line("  Created: {$created}");
        $this->line("  Updated: {$updated}");
        $this->line("  Skipped: {$skipped}");
        $this->line('  Total:   '.($created + $updated + $skipped));
    }
}
