<?php

namespace Tests\Feature;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqApprovedResponse;
use App\Models\FaqEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailQuestionAnswerDraftReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_a_draft_saves_the_final_answer_as_the_best_matched_faq_override(): void
    {
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
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        $draft->markReviewed(EmailQuestionAnswerDraft::StatusApproved, reviewerId: User::factory()->create()->id);

        $override = FaqApprovedResponse::query()->where('faq_entry_id', $bestMatch->id)->sole();

        $this->assertSame('Operator-edited final answer.', $override->approved_response);
        $this->assertEqualsWithDelta(0.93, $override->match_similarity, 0.0001);
        $this->assertSame(0, FaqApprovedResponse::query()->where('faq_entry_id', $secondMatch->id)->count());
    }

    public function test_approving_a_draft_updates_an_existing_override_for_the_same_faq(): void
    {
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
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        $draft->markReviewed(EmailQuestionAnswerDraft::StatusApproved, reviewerId: User::factory()->create()->id);

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
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        $draft->markReviewed(EmailQuestionAnswerDraft::StatusRejected, reviewerId: User::factory()->create()->id);
        $draft->markReviewed(EmailQuestionAnswerDraft::StatusNeedsRevision, reviewerId: User::factory()->create()->id);

        $this->assertSame(0, FaqApprovedResponse::query()->count());
    }

    public function test_approving_a_draft_without_faq_matches_does_not_error(): void
    {
        $question = EmailQuestion::factory()->create();

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'final_answer' => 'Answer with no retrieved matches.',
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        $draft->markReviewed(EmailQuestionAnswerDraft::StatusApproved, reviewerId: User::factory()->create()->id);

        $this->assertSame(0, FaqApprovedResponse::query()->count());
    }
}
