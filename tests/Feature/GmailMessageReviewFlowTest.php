<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\GmailMessages\Pages\ManageGmailMessages;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailTemplate;
use App\Models\EmailThreadDraft;
use App\Models\GmailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class GmailMessageReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_message_list_renders_for_a_reviewer(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-list']);
        EmailQuestion::factory()->for($message, 'message')->create();

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$message]);
    }

    public function test_the_view_action_mounts_without_error_for_a_message_with_no_questions(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $message = GmailMessage::factory()->create();

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors();
    }

    public function test_the_view_action_mounts_without_error_for_a_message_with_mixed_question_states(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-view']);

        $approvedQuestion = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create(['question_text' => 'Wie funktioniert das Investment?']);
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $approvedQuestion->id,
            'final_answer' => 'Sie investieren direkt.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
        ]);

        EmailQuestion::factory()
            ->for($message, 'message')
            ->create(['question_text' => 'Gibt es eine Mindestanlage?']);

        EmailThreadDraft::factory()->create([
            'thread_id' => 'thread-view',
            'subject' => 'Ihre Webinarfrage',
            'body' => '<p>Composed body</p>',
        ]);

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors();
    }

    public function test_the_view_action_hides_the_raw_message_body_and_faq_matches(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create([
            'text_body' => 'Geheimer Nachrichtentext',
            'html_body' => '<p>Geheimer HTML-Text</p>',
        ]);
        EmailQuestion::factory()->for($message, 'message')->create();

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors()
            ->assertMountedActionModalDontSee('Geheimer Nachrichtentext')
            ->assertMountedActionModalDontSee('Geheimer HTML-Text')
            ->assertMountedActionModalDontSee('FAQ Matches')
            ->assertMountedActionModalDontSee('Retrieve FAQ matches');
    }

    public function test_the_view_action_shows_the_ai_classification(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()
            ->for($message, 'message')
            ->classifiedAsValid()
            ->create(['question_text' => 'Wie funktioniert das Investment?']);

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Valid question')
            ->assertMountedActionModalSee('92%')
            ->assertMountedActionModalSee('This is a customer FAQ question.');
    }

    public function test_the_view_action_hides_the_answer_section_for_a_noise_question(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestion::ReviewStatusNoise)
            ->create(['question_text' => 'ich sehe euch nicht, hore den Ton nicht']);

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors()
            ->assertMountedActionModalDontSee('Regenerate draft answer')
            ->assertMountedActionModalDontSee('No draft generated yet');
    }

    public function test_the_processed_icon_distinguishes_pending_drafted_and_resolved_messages(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $pendingMessage = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($pendingMessage, 'message')->create();

        $notYetExtractedMessage = GmailMessage::factory()->create();

        $draftedMessage = GmailMessage::factory()->create(['thread_id' => 'thread-drafted']);
        $draftedQuestion = EmailQuestion::factory()
            ->for($draftedMessage, 'message')
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $draftedQuestion->id,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
        ]);
        EmailThreadDraft::factory()->create(['thread_id' => 'thread-drafted']);

        $resolvedMessage = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($resolvedMessage, 'message')->reviewedAs(EmailQuestion::ReviewStatusNoise)->create();

        $this->actingAs($reviewer);

        $component = Livewire::test(ManageGmailMessages::class)->assertOk();

        $component
            ->assertTableColumnStateSet('processed', 'pending', record: $pendingMessage->load('questions.answerDraft', 'threadDraft'))
            ->assertTableColumnStateSet('processed', 'pending', record: $notYetExtractedMessage->load('questions.answerDraft', 'threadDraft'))
            ->assertTableColumnStateSet('processed', 'drafted', record: $draftedMessage->load('questions.answerDraft', 'threadDraft'))
            ->assertTableColumnStateSet('processed', 'resolved', record: $resolvedMessage->load('questions.answerDraft', 'threadDraft'));
    }

    public function test_the_answer_section_appears_as_soon_as_a_question_is_marked_valid(): void
    {
        Queue::fake();

        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->classifiedAsValid()
            ->create(['question_text' => 'ich sehe euch nicht, hore den Ton nicht']);

        $this->actingAs($reviewer);

        $component = Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertMountedActionModalDontSee('Regenerate draft answer');

        $question->markReviewed(EmailQuestion::ReviewStatusValid, $reviewer->id);

        $component
            ->call('$refresh')
            ->assertMountedActionModalSee('Regenerate draft answer');
    }

    public function test_the_view_action_shows_the_answer_section_for_a_valid_question(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);

        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create(['question_text' => 'Wie funktioniert das Investment?']);

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Regenerate draft answer');
    }

    public function test_an_admin_sees_the_edit_template_link_in_the_composed_email_section(): void
    {
        EmailTemplate::factory()->create();

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($message, 'message')->create();

        $this->actingAs($admin);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Edit template');
    }

    public function test_a_reviewer_does_not_see_the_edit_template_link(): void
    {
        EmailTemplate::factory()->create();

        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($message, 'message')->create();

        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->mountTableAction('view', $message)
            ->assertOk()
            ->assertHasNoActionErrors()
            ->assertMountedActionModalDontSee('Edit template');
    }
}
