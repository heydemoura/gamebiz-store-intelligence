<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\GameReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['title', 'slug', 'platform', 'publisher', 'developer', 'release_date', 'release_dates_raw', 'source', 'source_url'])]
class GameReference extends Model
{
    /** @use HasFactory<GameReferenceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'release_date' => 'date',
            'release_dates_raw' => 'array',
        ];
    }

    /**
     * @return HasMany<Listing, $this>
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * @return HasManyThrough<PriceSnapshot, Listing, $this>
     */
    public function priceSnapshots(): HasManyThrough
    {
        return $this->hasManyThrough(PriceSnapshot::class, Listing::class);
    }
}
