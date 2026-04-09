<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeMarketplace;
use App\Models\Marketplace;
use App\Models\SearchTerm;
use App\Services\Scrapers\ScraperManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:schedule')]
#[Description('Dispatch scrape jobs for all marketplaces that are due')]
class ScrapeScheduleCommand extends Command
{
    public function handle(ScraperManager $scraperManager): int
    {
        $dueMarketplaces = Marketplace::where('is_active', true)
            ->get()
            ->filter(fn (Marketplace $mp) => $mp->isDue() && $scraperManager->has($mp->slug));

        if ($dueMarketplaces->isEmpty()) {
            $this->info('No marketplaces are due for scraping.');

            return self::SUCCESS;
        }

        $terms = SearchTerm::where('is_active', true)->get();

        if ($terms->isEmpty()) {
            $this->error('No active search terms configured.');

            return self::FAILURE;
        }

        $dispatched = 0;

        foreach ($dueMarketplaces as $marketplace) {
            foreach ($terms as $term) {
                dispatch(new ScrapeMarketplace($marketplace, $term->term));
                $dispatched++;
            }

            $this->info("Dispatched {$terms->count()} jobs for {$marketplace->name}");
        }

        $this->info("Total jobs dispatched: {$dispatched}");

        return self::SUCCESS;
    }
}
