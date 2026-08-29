<?php

namespace Tests\Feature;

use App\Enums\AnswerDraftStatus;
use App\Jobs\ComposeEmailThreadDraft;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqApprovedResponse;
use App\Models\FaqEntry;
use App\Models\GmailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EmailQuestionAnswerDraftReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_draft_saves_the_final_answer_as_the_best_matched_faq_override(): void
    {
        Queue::fake();

        $question = EmailQuestion::factory()->create();

        $bestMatch = FaqEntry::factory()->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
            'faq_entry_id' => $bestMatch->id,
            'rank' => 1,
            'similarity' => 0.93,
        ]);

        $secondMatch = FaqEntry::factory()->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
            'faq_entry_id' => $secondMatch->id,
            'rank' => 2,
            'similarity' => 0.81,
        ]);

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'generated_answer' => 'Original AI draft.',
            'final_answer' => 'Operator-edited final answer.',
            'status' => AnswerDraftStatus::Draft,
        ]);

        $draft->markReviewed(AnswerDraftStatus::Approved, reviewerId: User::factory()->create()->id);

        $override = FaqApprovedResponse::query()->where('faq_entry_id', $bestMatch->id)->sole();

        $this->assertSame('Operator-edited final answer.', $override->approved_response);
        $this->assertEqualsWithDelta(0.93, $override->match_similarity, 0.0001);
        $this->assertSame(0, FaqApprovedResponse::query()->where('faq_entry_id', $secondMatch->id)->count());
    }

    public function test_approving_a_draft_updates_an_existing_override_for_the_same_faq(): void
    {
        Queue::fake();

        $question = EmailQuestion::factory()->create();

        $faqEntry = FaqEntry::factory()->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
            'faq_entry_id' => $faqEntry->id,
            'rank' => 1,
            'similarity' => 0.9,
        ]);

        FaqApprovedResponse::query()->create([
            'faq_entry_id' => $faqEntry->id,
            'approved_response' => 'Stale override text.',
            'match_similarity' => 0.5,
        ]);

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Fresh operator-approved answer.',
            'status' => AnswerDraftStatus::Draft,
        ]);

        $draft->markReviewed(AnswerDraftStatus::Approved, reviewerId: User::factory()->create()->id);

        $this->assertSame(1, FaqApprovedResponse::query()->where('faq_entry_id', $faqEntry->id)->count());
        $this->assertSame(
            'Fresh operator-approved answer.',
            FaqApprovedResponse::query()->where('faq_entry_id', $faqEntry->id)->sole()->approved_response,
        );
    }

    public function test_rejecting_or_requesting_revision_does_not_write_a_faq_override(): void
    {
        $question = EmailQuestion::factory()->create();

        $faqEntry = FaqEntry::factory()->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
            'faq_entry_id' => $faqEntry->id,
            'rank' => 1,
        ]);

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Not good enough.',
            'status' => AnswerDraftStatus::Draft,
        ]);

        $draft->markReviewed(AnswerDraftStatus::Rejected, reviewerId: User::factory()->create()->id);
        $draft->markReviewed(AnswerDraftStatus::NeedsRevision, reviewerId: User::factory()->create()->id);

        $this->assertSame(0, FaqApprovedResponse::query()->count());
    }

    public function test_approving_a_draft_without_faq_matches_does_not_error(): void
    {
        Queue::fake();

        $question = EmailQuestion::factory()->create();

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Answer with no retrieved matches.',
            'status' => AnswerDraftStatus::Draft,
        ]);

        $draft->markReviewed(AnswerDraftStatus::Approved, reviewerId: User::factory()->create()->id);

        $this->assertSame(0, FaqApprovedResponse::query()->count());
    }

    public function test_approving_a_draft_dispatches_thread_draft_composition(): void
    {
        Queue::fake();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-approve']);
        $question = EmailQuestion::factory()->for($message, 'message')->create();

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Answer.',
            'status' => AnswerDraftStatus::Draft,
        ]);

        $draft->markReviewed(AnswerDraftStatus::Approved, reviewerId: User::factory()->create()->id);

        Queue::assertPushed(
            ComposeEmailThreadDraft::class,
            fn (ComposeEmailThreadDraft $job): bool => $job->threadId === 'thread-approve',
        );
    }

    public function test_editing_final_answer_while_already_approved_redispatches_thread_draft_composition(): void
    {
        $message = GmailMessage::factory()->create(['thread_id' => 'thread-edit']);
        $question = EmailQuestion::factory()->for($message, 'message')->create();

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Original.',
            'status' => AnswerDraftStatus::Approved,
        ]);

        Queue::fake();

        $draft->update(['final_answer' => 'Edited after approval.']);
        $draft->syncApprovedSideEffects();

        Queue::assertPushed(
            ComposeEmailThreadDraft::class,
            fn (ComposeEmailThreadDraft $job): bool => $job->threadId === 'thread-edit',
        );
    }

    public function test_editing_final_answer_while_not_approved_does_not_dispatch_thread_draft_composition(): void
    {
        Queue::fake();

        $message = GmailMessage::factory()->create(['thread_id' => 'thread-draft-only']);
        $question = EmailQuestion::factory()->for($message, 'message')->create();

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Original.',
            'status' => AnswerDraftStatus::Draft,
        ]);

        $draft->update(['final_answer' => 'Still just a draft.']);
        $draft->syncApprovedSideEffects();

        Queue::assertNotPushed(ComposeEmailThreadDraft::class);
    }
}
