<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Jobs\ScrapeMarketplace;
use App\Models\GameReference;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\PriceSnapshot;
use App\Services\Scrapers\ScraperManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GameReferenceController extends Controller
{
    public function index(Request $request): Response
    {
        $platform = $request->input('platform', 'ps4');

        $query = GameReference::query()
            ->where('platform', $platform)
            ->withCount(['listings as active_listings_count' => function ($q) {
                $q->where('is_available', true);
            }])
            ->withAvg(['listings as average_price_cents' => function ($q) {
                $q->where('is_available', true)->where('price_cents', '>', 0);
            }], 'price_cents');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        $sortField = $request->input('sort', 'title');
        $sortDirection = $request->input('direction', 'asc');

        $allowedSorts = ['title', 'release_date', 'active_listings_count', 'average_price_cents'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $games = $query->paginate(30)->withQueryString();

        return Inertia::render('catalog/index', [
            'games' => $games,
            'platforms' => collect(Platform::cases())
                ->filter(fn (Platform $p) => $p !== Platform::Other)
                ->map(fn (Platform $p) => [
                    'value' => $p->value,
                    'label' => $p->label(),
                ])->values(),
            'filters' => $request->only(['platform', 'search', 'sort', 'direction']),
        ]);
    }

    public function show(GameReference $gameReference): Response
    {
        $listings = Listing::where('game_reference_id', $gameReference->id)
            ->where('is_available', true)
            ->with(['marketplace', 'tags'])
            ->orderBy('price_cents')
            ->get()
            ->map(fn (Listing $l) => [
                'id' => $l->id,
                'title' => $l->title,
                'price_cents' => $l->price_cents,
                'condition' => $l->condition->value,
                'condition_label' => $l->condition->label(),
                'listing_url' => $l->listing_url,
                'image_url' => $l->image_url,
                'marketplace' => $l->marketplace->name,
                'seller_name' => $l->seller_name,
                'last_seen_at' => $l->last_seen_at->toDateTimeString(),
                'tags' => $l->tags->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'color' => $t->color,
                ])->values()->all(),
            ]);

        $activePrices = Listing::where('game_reference_id', $gameReference->id)
            ->where('is_available', true)
            ->where('price_cents', '>', 0)
            ->pluck('price_cents');

        $priceStats = [
            'min' => $activePrices->min() ?? 0,
            'max' => $activePrices->max() ?? 0,
            'avg' => (int) ($activePrices->avg() ?? 0),
            'median' => $activePrices->isNotEmpty() ? (int) $activePrices->median() : 0,
            'count' => $activePrices->count(),
        ];

        $priceHistory = PriceSnapshot::whereHas('listing', function ($q) use ($gameReference) {
            $q->where('game_reference_id', $gameReference->id);
        })
            ->select(
                DB::raw('DATE(scraped_at) as date'),
                DB::raw('AVG(price_cents) as avg_price'),
                DB::raw('MIN(price_cents) as min_price'),
                DB::raw('MAX(price_cents) as max_price'),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->limit(90)
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'avg_price' => (int) $row->avg_price,
                'min_price' => (int) $row->min_price,
                'max_price' => (int) $row->max_price,
            ]);

        $scraperManager = app(ScraperManager::class);
        $marketplaces = Marketplace::where('is_active', true)
            ->get()
            ->filter(fn (Marketplace $mp) => $scraperManager->has($mp->slug))
            ->map(fn (Marketplace $mp) => [
                'id' => $mp->id,
                'name' => $mp->name,
                'slug' => $mp->slug,
            ])->values();

        return Inertia::render('catalog/show', [
            'game' => [
                'id' => $gameReference->id,
                'title' => $gameReference->title,
                'slug' => $gameReference->slug,
                'platform' => $gameReference->platform->value,
                'platform_label' => $gameReference->platform->label(),
                'publisher' => $gameReference->publisher,
                'developer' => $gameReference->developer,
                'release_date' => $gameReference->release_date?->toDateString(),
                'source' => $gameReference->source,
                'source_url' => $gameReference->source_url,
            ],
            'listings' => $listings,
            'priceStats' => $priceStats,
            'priceHistory' => $priceHistory,
            'marketplaces' => $marketplaces,
        ]);
    }

    public function scrape(Request $request, GameReference $gameReference): RedirectResponse
    {
        $validated = $request->validate([
            'marketplace_id' => ['required', 'exists:marketplaces,id'],
        ]);

        $marketplace = Marketplace::findOrFail($validated['marketplace_id']);

        dispatch(new ScrapeMarketplace($marketplace, $gameReference->title));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Scraping \"{$gameReference->title}\" on {$marketplace->name}. Results will appear shortly.",
        ]);

        return back();
    }
}
