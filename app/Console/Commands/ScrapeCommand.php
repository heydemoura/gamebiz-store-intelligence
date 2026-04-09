<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeMarketplace;
use App\Models\Marketplace;
use App\Models\SearchTerm;
use App\Services\Scrapers\ScraperManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Dusk\Chrome\ChromeProcess;
use Symfony\Component\Process\Process;

#[Signature('scrape {marketplace? : The marketplace slug to scrape} {--term= : A specific search term to use} {--sync : Run synchronously instead of dispatching to queue}')]
#[Description('Scrape a marketplace for used game listings')]
class ScrapeCommand extends Command
{
    private ?Process $chromeProcess = null;

    public function handle(ScraperManager $scraperManager): int
    {
        $slug = $this->argument('marketplace');

        if ($slug === null) {
            $this->info('Available scrapers: ' . implode(', ', $scraperManager->available()));

            return self::SUCCESS;
        }

        if (! $scraperManager->has($slug)) {
            $this->error("No scraper registered for: {$slug}");
            $this->info('Available: ' . implode(', ', $scraperManager->available()));

            return self::FAILURE;
        }

        $marketplace = Marketplace::where('slug', $slug)->first();

        if ($marketplace === null) {
            $this->error("Marketplace not found in database: {$slug}");
            $this->info('Run php artisan db:seed --class=MarketplaceSeeder to seed marketplaces.');

            return self::FAILURE;
        }

        // Start ChromeDriver for browser-based scrapers
        $needsBrowser = $slug === 'facebook';

        if ($needsBrowser && $this->option('sync')) {
            if (! $this->startChromeDriver()) {
                return self::FAILURE;
            }
        }

        $specificTerm = $this->option('term');
        $terms = $specificTerm
            ? [['term' => $specificTerm]]
            : SearchTerm::where('is_active', true)->get()->toArray();

        if (empty($terms)) {
            $this->error('No search terms configured. Add terms to search_terms table.');
            $this->stopChromeDriver();

            return self::FAILURE;
        }

        $sync = $this->option('sync');

        try {
            foreach ($terms as $term) {
                $termValue = is_array($term) ? $term['term'] : $term;
                $this->info("Scraping {$marketplace->name} for: {$termValue}");

                $job = new ScrapeMarketplace($marketplace, $termValue);

                if ($sync) {
                    dispatch_sync($job);
                    $this->info('  Completed synchronously.');
                } else {
                    if ($needsBrowser) {
                        $this->warn('  Note: Facebook scraper requires ChromeDriver running. Use --sync or ensure ChromeDriver is started.');
                    }

                    dispatch($job);
                    $this->info('  Dispatched to queue.');
                }
            }
        } finally {
            $this->stopChromeDriver();
        }

        return self::SUCCESS;
    }

    private function startChromeDriver(): bool
    {
        $cookieFile = storage_path('app/facebook_cookies.json');

        if (! file_exists($cookieFile)) {
            $this->error('Facebook cookies not found. Run "php artisan facebook:login" first.');

            return false;
        }

        $this->info('Starting ChromeDriver...');

        $this->chromeProcess = (new ChromeProcess)->toProcess(['--port=9515']);
        $this->chromeProcess->start();

        sleep(2);

        if (! $this->chromeProcess->isRunning()) {
            $this->error('Failed to start ChromeDriver.');

            return false;
        }

        $this->info('ChromeDriver started.');

        return true;
    }

    private function stopChromeDriver(): void
    {
        if ($this->chromeProcess !== null && $this->chromeProcess->isRunning()) {
            $this->chromeProcess->stop();
            $this->info('ChromeDriver stopped.');
        }
    }
}
