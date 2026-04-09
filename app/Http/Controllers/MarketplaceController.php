<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeMarketplace as ScrapeMarketplaceJob;
use App\Models\Marketplace;
use App\Models\SearchTerm;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function index(): Response
    {
        $marketplaces = Marketplace::withCount(['listings', 'listings as active_listings_count' => function ($q) {
            $q->where('is_available', true);
        }])->get()->map(fn (Marketplace $mp) => [
            'id' => $mp->id,
            'name' => $mp->name,
            'slug' => $mp->slug,
            'base_url' => $mp->base_url,
            'is_active' => $mp->is_active,
            'scrape_interval_minutes' => $mp->scrape_interval_minutes,
            'last_scraped_at' => $mp->last_scraped_at?->toDateTimeString(),
            'listings_count' => $mp->listings_count,
            'active_listings_count' => $mp->active_listings_count,
        ]);

        return Inertia::render('marketplaces/index', [
            'marketplaces' => $marketplaces,
        ]);
    }

    public function toggleActive(Marketplace $marketplace): RedirectResponse
    {
        $marketplace->update(['is_active' => ! $marketplace->is_active]);

        $status = $marketplace->is_active ? 'activated' : 'deactivated';
        Inertia::flash('toast', ['type' => 'success', 'message' => "{$marketplace->name} {$status}."]);

        return back();
    }

    public function scrapeNow(Marketplace $marketplace): RedirectResponse
    {
        $terms = SearchTerm::where('is_active', true)->get();

        foreach ($terms as $term) {
            dispatch(new ScrapeMarketplaceJob($marketplace, $term->term));
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Dispatched {$terms->count()} scrape jobs for {$marketplace->name}.",
        ]);

        return back();
    }
}
