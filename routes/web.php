<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MarketplaceController;
use App\Http\Controllers\SearchTermController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('games', [GameController::class, 'index'])->name('games.index');
    Route::get('games/export', [GameController::class, 'export'])->name('games.export');
    Route::get('games/{game}', [GameController::class, 'show'])->name('games.show');

    Route::get('listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('listings/export', [ListingController::class, 'export'])->name('listings.export');
    Route::patch('listings/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::post('listings/{listing}/tags/{tag}', [ListingController::class, 'toggleTag'])->name('listings.toggle-tag');

    Route::get('marketplaces', [MarketplaceController::class, 'index'])->name('marketplaces.index');
    Route::patch('marketplaces/{marketplace}', [MarketplaceController::class, 'toggleActive'])->name('marketplaces.toggle');
    Route::post('marketplaces/{marketplace}/scrape', [MarketplaceController::class, 'scrapeNow'])->name('marketplaces.scrape');

    Route::get('search-terms', [SearchTermController::class, 'index'])->name('search-terms.index');
    Route::post('search-terms', [SearchTermController::class, 'store'])->name('search-terms.store');
    Route::delete('search-terms/{searchTerm}', [SearchTermController::class, 'destroy'])->name('search-terms.destroy');
});

require __DIR__.'/settings.php';
