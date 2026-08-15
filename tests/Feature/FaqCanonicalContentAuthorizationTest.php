<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\FaqEntry;
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

    public function test_admin_cannot_manually_mutate_faq_entries(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $faqEntry = FaqEntry::factory()->create();

        $this->assertFalse($admin->can('create', FaqEntry::class));
        $this->assertFalse($admin->can('update', $faqEntry));
        $this->assertFalse($admin->can('delete', $faqEntry));
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

    public function test_admin_can_view_email_templates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/email-templates')->assertOk();
    }

    public function test_reviewer_cannot_view_email_templates(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $this->actingAs($reviewer)->get('/admin/email-templates')->assertForbidden();
    }

    public function test_admin_can_view_email_questions(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/email-questions')->assertOk();
    }

    public function test_reviewer_cannot_view_email_questions(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $this->actingAs($reviewer)->get('/admin/email-questions')->assertForbidden();
    }
}
