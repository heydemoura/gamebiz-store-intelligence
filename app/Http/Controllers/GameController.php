<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Models\Game;
use App\Models\Listing;
use App\Models\PriceSnapshot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GameController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Game::query()
            ->withCount(['listings as active_listings_count' => function ($q) {
                $q->where('is_available', true);
            }])
            ->withAvg(['listings as average_price_cents' => function ($q) {
                $q->where('is_available', true)->where('price_cents', '>', 0);
            }], 'price_cents');

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->input('search') . '%');
        }

        if ($request->filled('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        $sortField = $request->input('sort', 'title');
        $sortDirection = $request->input('direction', 'asc');

        $allowedSorts = ['title', 'active_listings_count', 'average_price_cents', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $games = $query->paginate(24)->withQueryString();

        return Inertia::render('games/index', [
            'games' => $games,
            'platforms' => collect(Platform::cases())->map(fn (Platform $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'filters' => $request->only(['search', 'platform', 'sort', 'direction']),
        ]);
    }

    public function show(Game $game): Response
    {
        $listings = Listing::where('game_id', $game->id)
            ->where('is_available', true)
            ->with('marketplace')
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
            ]);

        $activePrices = Listing::where('game_id', $game->id)
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

        $priceHistory = PriceSnapshot::whereHas('listing', function ($q) use ($game) {
            $q->where('game_id', $game->id);
        })
            ->select(
                DB::raw("DATE(scraped_at) as date"),
                DB::raw('AVG(price_cents) as avg_price'),
                DB::raw('MIN(price_cents) as min_price'),
                DB::raw('MAX(price_cents) as max_price'),
                DB::raw('COUNT(*) as snapshot_count'),
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

        return Inertia::render('games/show', [
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'slug' => $game->slug,
                'platform' => $game->platform->value,
                'platform_label' => $game->platform->label(),
                'year' => $game->year,
                'cover_image_url' => $game->cover_image_url,
                'is_verified' => $game->is_verified,
            ],
            'listings' => $listings,
            'priceStats' => $priceStats,
            'priceHistory' => $priceHistory,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Game::query()
            ->withCount(['listings as active_listings_count' => function ($q) {
                $q->where('is_available', true);
            }])
            ->withAvg(['listings as average_price_cents' => function ($q) {
                $q->where('is_available', true)->where('price_cents', '>', 0);
            }], 'price_cents');

        if ($request->filled('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Title', 'Platform', 'Year', 'Active Listings', 'Avg Price (BRL)', 'Verified']);

            $query->orderBy('title')->chunk(100, function ($games) use ($handle) {
                foreach ($games as $game) {
                    fputcsv($handle, [
                        $game->title,
                        $game->platform->label(),
                        $game->year ?? '',
                        $game->active_listings_count,
                        $game->average_price_cents ? number_format($game->average_price_cents / 100, 2) : '',
                        $game->is_verified ? 'Yes' : 'No',
                    ]);
                }
            });

            fclose($handle);
        }, 'games-export-' . date('Y-m-d') . '.csv');
    }
}
