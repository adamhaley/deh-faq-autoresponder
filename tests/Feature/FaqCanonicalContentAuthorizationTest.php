<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqCanonicalContentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_faq_entries(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/faq-entries')->assertOk();
    }

    public function test_reviewer_cannot_view_faq_entries(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $this->actingAs($reviewer)->get('/admin/faq-entries')->assertForbidden();
    }

    public function test_admin_can_view_faq_approved_responses(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/faq-approved-responses')->assertOk();
    }

    public function test_reviewer_cannot_view_faq_approved_responses(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $this->actingAs($reviewer)->get('/admin/faq-approved-responses')->assertForbidden();
    }
}
