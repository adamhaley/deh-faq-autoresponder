<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_users_can_access_admin_panel(): void
    {
        $user = User::factory()->make(['is_active' => true, 'role' => UserRole::Viewer]);

        $this->assertTrue($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_inactive_users_cannot_access_admin_panel(): void
    {
        $user = User::factory()->make(['is_active' => false, 'role' => UserRole::Admin]);

        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_viewers_cannot_review_responses(): void
    {
        $user = new User(['role' => UserRole::Viewer]);

        $this->assertFalse($user->canReviewResponses());
    }
}
