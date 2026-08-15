<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Filament's NavigationItem::getSort() defaults an unset sort to -1, so
     * every resource without an explicit navigationSort ties there. Dashboard
     * (::class navigationSort -2, unchanged) must render first; the Gmail
     * message resource (navigation label "Webinar Responses") is pinned to
     * -2 as well to win that tie via discovery order and land second, ahead
     * of the untouched (-1 default) resources.
     */
    public function test_dashboard_then_webinar_responses_lead_the_navigation(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $content = $this->actingAs($admin)->get('/admin')->assertOk()->getContent();

        preg_match_all('/fi-sidebar-item-label[^>]*>\s*([^<]+)/', $content, $matches);
        $labels = array_map(trim(...), $matches[1]);

        $this->assertSame(['Dashboard', 'Webinar Responses'], array_slice($labels, 0, 2));
    }
}
