<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use App\Models\SearchTerm;
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
    }
}
