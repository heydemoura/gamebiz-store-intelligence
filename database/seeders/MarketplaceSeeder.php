<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use App\Models\SearchTerm;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $marketplaces = [
            [
                'name' => 'OLX',
                'slug' => 'olx',
                'base_url' => 'https://www.olx.com.br',
                'scrape_interval_minutes' => 60,
                'rate_limit_per_minute' => 10,
            ],
            [
                'name' => 'Enjoei',
                'slug' => 'enjoei',
                'base_url' => 'https://www.enjoei.com.br',
                'scrape_interval_minutes' => 60,
                'rate_limit_per_minute' => 10,
            ],
            [
                'name' => 'Mercado Livre',
                'slug' => 'mercadolivre',
                'base_url' => 'https://www.mercadolivre.com.br',
                'scrape_interval_minutes' => 120,
                'rate_limit_per_minute' => 5,
            ],
            [
                'name' => 'Amazon Brasil',
                'slug' => 'amazon',
                'base_url' => 'https://www.amazon.com.br',
                'scrape_interval_minutes' => 120,
                'rate_limit_per_minute' => 5,
            ],
            [
                'name' => 'Meu Game Usado',
                'slug' => 'meugameusado',
                'base_url' => 'https://www.meugameusado.com.br',
                'scrape_interval_minutes' => 60,
                'rate_limit_per_minute' => 15,
            ],
            [
                'name' => 'Facebook Marketplace',
                'slug' => 'facebook',
                'base_url' => 'https://www.facebook.com/marketplace/fortaleza',
                'scrape_interval_minutes' => 180,
                'rate_limit_per_minute' => 3,
            ],
        ];

        foreach ($marketplaces as $marketplace) {
            Marketplace::updateOrCreate(
                ['slug' => $marketplace['slug']],
                $marketplace,
            );
        }

        $searchTerms = [
            ['term' => 'videogame usado', 'is_category' => true],
            ['term' => 'jogo usado ps4', 'is_category' => true],
            ['term' => 'jogo usado ps5', 'is_category' => true],
            ['term' => 'jogo usado xbox', 'is_category' => true],
            ['term' => 'jogo usado switch', 'is_category' => true],
        ];

        foreach ($searchTerms as $term) {
            SearchTerm::updateOrCreate(
                ['term' => $term['term']],
                $term,
            );
        }

        $tags = [
            ['name' => 'Scam', 'slug' => 'scam', 'color' => '#ef4444'],
            ['name' => 'Good Deal', 'slug' => 'good-deal', 'color' => '#22c55e'],
            ['name' => 'Needs Repair', 'slug' => 'needs-repair', 'color' => '#f59e0b'],
            ['name' => 'Overpriced', 'slug' => 'overpriced', 'color' => '#f97316'],
            ['name' => 'Fully Working', 'slug' => 'fully-working', 'color' => '#3b82f6'],
            ['name' => 'Complete with Accessories', 'slug' => 'complete-with-accessories', 'color' => '#8b5cf6'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['slug' => $tag['slug']],
                $tag,
            );
        }
    }
}
