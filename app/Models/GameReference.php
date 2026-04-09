<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\GameReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
