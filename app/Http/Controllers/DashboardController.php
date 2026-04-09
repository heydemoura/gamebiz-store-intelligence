<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Listing;
use App\Models\Marketplace;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $totalGames = Game::count();
        $totalListings = Listing::where('is_available', true)->count();
        $averagePriceCents = (int) Listing::where('is_available', true)
            ->where('price_cents', '>', 0)
            ->avg('price_cents');
        $totalMarketplaces = Marketplace::where('is_active', true)->count();

        $recentListings = Listing::with(['game', 'marketplace'])
            ->where('is_available', true)
            ->where('price_cents', '>', 0)
            ->orderByDesc('last_seen_at')
            ->limit(10)
            ->get()
            ->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'title' => $listing->title,
                'price_cents' => $listing->price_cents,
                'condition' => $listing->condition->value,
                'condition_label' => $listing->condition->label(),
                'listing_url' => $listing->listing_url,
                'marketplace' => $listing->marketplace->name,
                'game_title' => $listing->game?->title,
                'last_seen_at' => $listing->last_seen_at->toDateTimeString(),
            ]);

        $bestDeals = Listing::query()
            ->select('listings.*')
            ->join(
                DB::raw('(SELECT game_id as gid, AVG(price_cents) as avg_price FROM listings WHERE is_available = 1 AND price_cents > 0 AND game_id IS NOT NULL GROUP BY game_id HAVING COUNT(*) >= 2) as game_avgs'),
                'listings.game_id',
                '=',
                'game_avgs.gid',
            )
            ->where('listings.is_available', true)
            ->where('listings.price_cents', '>', 0)
            ->whereNotNull('listings.game_id')
            ->whereRaw('listings.price_cents < game_avgs.avg_price * 0.8')
            ->with(['game', 'marketplace'])
            ->orderBy('listings.price_cents')
            ->limit(10)
            ->get()
            ->map(fn (Listing $listing) => [
                'id' => $listing->id,
                'title' => $listing->title,
                'price_cents' => $listing->price_cents,
                'condition' => $listing->condition->value,
                'condition_label' => $listing->condition->label(),
                'listing_url' => $listing->listing_url,
                'marketplace' => $listing->marketplace->name,
                'game_title' => $listing->game?->title,
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'totalGames' => $totalGames,
                'totalListings' => $totalListings,
                'averagePriceCents' => $averagePriceCents,
                'totalMarketplaces' => $totalMarketplaces,
            ],
            'recentListings' => $recentListings,
            'bestDeals' => $bestDeals,
        ]);
    }
}
