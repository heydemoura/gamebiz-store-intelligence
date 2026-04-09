<?php

namespace App\Models;

use App\Enums\Platform;
use Database\Factories\SearchTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['term', 'platform', 'is_category', 'is_active'])]
class SearchTerm extends Model
{
    /** @use HasFactory<SearchTermFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'is_category' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
