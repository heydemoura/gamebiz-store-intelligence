<?php

namespace Tests\Feature\Controllers;

use App\Models\Marketplace;
use App\Models\SearchTerm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketplaceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function testMarketplaceIndexRendersAllMarketplaces(): void
    {
        Marketplace::factory()->count(3)->create();

        $this->actingAs($this->user)
            ->get('/marketplaces')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('marketplaces/index')
                ->has('marketplaces', 3)
            );
    }

    public function testToggleActiveFlipsMarketplaceStatus(): void
    {
        $marketplace = Marketplace::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->patch("/marketplaces/{$marketplace->id}")
            ->assertRedirect();

        $this->assertFalse($marketplace->fresh()->is_active);
    }

    public function testScrapeNowDispatchesJobs(): void
    {
        Queue::fake();

        $marketplace = Marketplace::factory()->create();
        SearchTerm::factory()->count(2)->create(['is_active' => true]);

        $this->actingAs($this->user)
            ->post("/marketplaces/{$marketplace->id}/scrape")
            ->assertRedirect();

        Queue::assertCount(2);
    }

    public function testSearchTermsCanBeCreated(): void
    {
        $this->actingAs($this->user)
            ->post('/search-terms', [
                'term' => 'God of War PS4',
                'is_category' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('search_terms', ['term' => 'God of War PS4']);
    }

    public function testSearchTermsCanBeDeleted(): void
    {
        $term = SearchTerm::factory()->create();

        $this->actingAs($this->user)
            ->delete("/search-terms/{$term->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('search_terms', ['id' => $term->id]);
    }
}
