<?php

namespace App\Models;

use Database\Factories\MarketplaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'base_url', 'is_active', 'scrape_interval_minutes', 'rate_limit_per_minute', 'last_scraped_at'])]
class Marketplace extends Model
{
    /** @use HasFactory<MarketplaceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_scraped_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function isDue(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->last_scraped_at === null) {
            return true;
        }

        return $this->last_scraped_at->addMinutes($this->scrape_interval_minutes)->isPast();
    }
}
