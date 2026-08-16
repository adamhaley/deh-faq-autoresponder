<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_renders_email_question_widgets(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Answer Performance')
            ->assertSee('Answer Similarity')
            ->assertSee('Question Classification')
            ->assertSee('Pending review')
            ->assertSee('AI and human classifications differed')
            ->assertSee('AI/Human Misalignment Rate')
            ->assertSee('Delta');
    }
}
