<?php

namespace App\Http\Controllers;

use App\Enums\GameCondition;
use App\Enums\Platform;
use App\Models\GameReference;
use App\Models\Listing;
use App\Models\Marketplace;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListingController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Listing::with(['game', 'gameReference', 'marketplace', 'tags'])
            ->where('is_available', true);

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        if ($request->filled('marketplace')) {
            $query->where('marketplace_id', $request->input('marketplace'));
        }

        if ($request->filled('condition')) {
            $query->where('condition', $request->input('condition'));
        }

        if ($request->filled('platform')) {
            $query->whereHas('game', function ($q) use ($request) {
                $q->where('platform', $request->input('platform'));
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price_cents', '>=', (int) $request->input('min_price') * 100);
        }

        if ($request->filled('max_price')) {
            $query->where('price_cents', '<=', (int) $request->input('max_price') * 100);
        }

        if ($request->input('tag') === 'untagged') {
            $query->whereDoesntHave('tags');
        } elseif ($request->filled('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('tags.slug', $request->input('tag'));
            });
        }

        $sortField = $request->input('sort', 'last_seen_at');
        $sortDirection = $request->input('direction', 'desc');

        $allowedSorts = ['price_cents', 'last_seen_at', 'title'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection === 'desc' ? 'desc' : 'asc');
        }

        $listings = $query->paginate(30)->withQueryString();

        $listings->through(fn (Listing $l) => [
            'id' => $l->id,
            'title' => $l->title,
            'price_cents' => $l->price_cents,
            'condition' => $l->condition->value,
            'condition_label' => $l->condition->label(),
            'listing_url' => $l->listing_url,
            'image_url' => $l->image_url,
            'marketplace' => $l->marketplace->name,
            'game_title' => $l->gameReference?->title ?? $l->game?->title,
            'game_id' => $l->game_id,
            'game_reference_id' => $l->game_reference_id,
            'game_reference_title' => $l->gameReference?->title,
            'game_platform' => $l->gameReference?->platform->label() ?? $l->game?->platform->label(),
            'seller_name' => $l->seller_name,
            'is_available' => $l->is_available,
            'last_seen_at' => $l->last_seen_at->toDateTimeString(),
            'tags' => $l->tags->map(fn (Tag $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slug' => $t->slug,
                'color' => $t->color,
            ])->values()->all(),
        ]);

        return Inertia::render('listings/index', [
            'listings' => $listings,
            'marketplaces' => Marketplace::orderBy('name')->get(['id', 'name']),
            'platforms' => collect(Platform::cases())->map(fn (Platform $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
            'conditions' => collect(GameCondition::cases())->map(fn (GameCondition $c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'tags' => Tag::orderBy('name')->get(['id', 'name', 'slug', 'color']),
            'gameReferences' => Inertia::optional(fn () => GameReference::orderBy('title')->get(['id', 'title', 'platform'])->map(fn (GameReference $g) => [
                'id' => $g->id,
                'label' => $g->title.' ('.$g->platform->label().')',
            ])),
            'filters' => $request->only(['search', 'marketplace', 'condition', 'platform', 'min_price', 'max_price', 'sort', 'direction', 'tag']),
        ]);
    }

    public function update(Request $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'condition' => ['required', 'string'],
            'listing_url' => ['required', 'string', 'max:2048'],
            'game_reference_id' => ['nullable', 'integer', 'exists:game_references,id'],
            'seller_name' => ['nullable', 'string', 'max:255'],
            'is_available' => ['required', 'boolean'],
        ]);

        $listing->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listing updated.']);

        return back();
    }

    public function toggleTag(Listing $listing, Tag $tag): RedirectResponse
    {
        $listing->tags()->toggle($tag->id);

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Listing::with(['game', 'marketplace', 'tags'])
            ->where('is_available', true)
            ->orderBy('price_cents');

        if ($request->filled('marketplace')) {
            $query->where('marketplace_id', $request->input('marketplace'));
        }

        if ($request->filled('platform')) {
            $query->whereHas('game', function ($q) use ($request) {
                $q->where('platform', $request->input('platform'));
            });
        }

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Title', 'Game', 'Platform', 'Price (BRL)', 'Condition', 'Marketplace', 'Seller', 'Tags', 'URL', 'Last Seen']);

            $query->chunk(100, function ($listings) use ($handle) {
                foreach ($listings as $listing) {
                    fputcsv($handle, [
                        $listing->title,
                        $listing->game?->title ?? '',
                        $listing->game?->platform->label() ?? '',
                        number_format($listing->price_cents / 100, 2),
                        $listing->condition->label(),
                        $listing->marketplace->name,
                        $listing->seller_name ?? '',
                        $listing->tags->pluck('name')->implode(', '),
                        $listing->listing_url,
                        $listing->last_seen_at->toDateTimeString(),
                    ]);
                }
            });

            fclose($handle);
        }, 'listings-export-'.date('Y-m-d').'.csv');
    }
}
