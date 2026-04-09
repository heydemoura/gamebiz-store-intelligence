<?php

namespace App\Http\Controllers;

use App\Enums\Platform;
use App\Models\SearchTerm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchTermController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('search-terms/index', [
            'searchTerms' => SearchTerm::orderBy('term')->get(),
            'platforms' => collect(Platform::cases())->map(fn (Platform $p) => [
                'value' => $p->value,
                'label' => $p->label(),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'term' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string'],
            'is_category' => ['boolean'],
        ]);

        SearchTerm::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Search term added.']);

        return back();
    }

    public function destroy(SearchTerm $searchTerm): RedirectResponse
    {
        $searchTerm->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Search term removed.']);

        return back();
    }
}
