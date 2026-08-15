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
            ->assertSee('Recent Gmail Messages')
            ->assertSee('Pending review')
            ->assertSee('AI/Human Misalignment Rate')
            ->assertSee('Recent AI/Human Misalignments');
    }

    public function test_reviewer_dashboard_renders_recent_gmail_messages_widget(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::Reviewer,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Recent Gmail Messages');
    }
}
