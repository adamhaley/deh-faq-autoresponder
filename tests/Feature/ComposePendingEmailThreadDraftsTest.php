<?php

namespace Tests\Feature;

use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionReviewStatus;
use App\Jobs\ComposeEmailThreadDraft;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailThreadDraft;
use App\Models\GmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComposePendingEmailThreadDraftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_composition_for_an_approved_thread_missing_a_draft(): void
    {
        Queue::fake();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-missing-draft']);

        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => AnswerDraftStatus::Approved,
        ]);

        $this->artisan('email-questions:compose-pending-drafts')
            ->expectsOutput('Queued draft composition for 1 thread(s).')
            ->assertSuccessful();

        Queue::assertPushed(
            ComposeEmailThreadDraft::class,
            fn (ComposeEmailThreadDraft $job): bool => $job->threadId === 'thread-missing-draft',
        );
    }

    public function test_it_skips_threads_that_already_have_a_draft(): void
    {
        Queue::fake();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-already-composed']);

        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => AnswerDraftStatus::Approved,
        ]);

        EmailThreadDraft::factory()->create(['thread_id' => 'thread-already-composed']);

        $this->artisan('email-questions:compose-pending-drafts')
            ->expectsOutput('Queued draft composition for 0 thread(s).')
            ->assertSuccessful();

        Queue::assertNotPushed(ComposeEmailThreadDraft::class);
    }

    public function test_it_skips_threads_without_an_approved_answer(): void
    {
        Queue::fake();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-not-ready']);

        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->reviewedAs(EmailQuestionReviewStatus::Valid)
            ->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => AnswerDraftStatus::Draft,
        ]);

        $this->artisan('email-questions:compose-pending-drafts')
            ->expectsOutput('Queued draft composition for 0 thread(s).')
            ->assertSuccessful();

        Queue::assertNotPushed(ComposeEmailThreadDraft::class);
    }
}
