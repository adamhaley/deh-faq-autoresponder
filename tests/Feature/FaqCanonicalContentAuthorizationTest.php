<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\EmailTemplate;
use App\Models\FaqEntry;
use App\Models\GmailMailbox;
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

    public function test_reviewer_can_view_and_update_email_templates(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create();

        $this->actingAs($reviewer)->get('/admin/email-templates')->assertOk();

        $this->assertTrue($reviewer->can('view', $emailTemplate));
        $this->assertTrue($reviewer->can('update', $emailTemplate));
        $this->assertFalse($reviewer->can('create', EmailTemplate::class));
        $this->assertFalse($reviewer->can('delete', $emailTemplate));
    }

    public function test_reviewer_cannot_view_authorized_emails(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $this->actingAs($reviewer)->get('/admin/authorized-emails')->assertForbidden();

        $this->assertFalse($reviewer->can('viewAny', AuthorizedEmail::class));
    }

    public function test_reviewer_cannot_view_gmail_mailboxes(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $gmailMailbox = GmailMailbox::factory()->create();

        $this->actingAs($reviewer)->get('/admin/gmail-mailboxes')->assertForbidden();

        $this->assertFalse($reviewer->can('view', $gmailMailbox));
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
