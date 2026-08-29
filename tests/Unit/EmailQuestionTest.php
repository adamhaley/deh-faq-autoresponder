<?php

namespace Tests\Unit;

use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionReviewStatus;
use App\Enums\FaqRetrievalStatus;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use Tests\TestCase;

class EmailQuestionTest extends TestCase
{
    public function test_reviewer_decision_cases_only_include_reviewer_decisions(): void
    {
        $this->assertSame([
            EmailQuestionReviewStatus::Valid,
            EmailQuestionReviewStatus::Noise,
            EmailQuestionReviewStatus::Unanswerable,
        ], EmailQuestionReviewStatus::reviewerDecisionCases());
    }

    public function test_reviewer_filter_cases_include_pending_but_not_needs_human(): void
    {
        $this->assertSame([
            EmailQuestionReviewStatus::PendingReview,
            EmailQuestionReviewStatus::Valid,
            EmailQuestionReviewStatus::Noise,
            EmailQuestionReviewStatus::Unanswerable,
        ], EmailQuestionReviewStatus::reviewerFilterCases());
    }

    public function test_active_async_pipeline_detects_faq_retrieval_work(): void
    {
        $question = EmailQuestion::factory()->create([
            'faq_retrieval_status' => FaqRetrievalStatus::Queued,
        ]);

        $this->assertTrue($question->hasActiveFaqRetrieval());
        $this->assertTrue($question->hasActiveAsyncPipeline());
        $this->assertTrue(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());

        $question->update([
            'faq_retrieval_status' => FaqRetrievalStatus::Completed,
        ]);

        $this->assertFalse($question->refresh()->hasActiveFaqRetrieval());
        $this->assertFalse($question->hasActiveAsyncPipeline());
        $this->assertFalse(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());
    }

    public function test_active_async_pipeline_detects_answer_draft_generation_work(): void
    {
        $question = EmailQuestion::factory()
            ->has(EmailQuestionAnswerDraft::factory()->state([
                'status' => AnswerDraftStatus::Generating,
            ]), 'answerDraft')
            ->create();

        $this->assertTrue($question->refresh()->hasActiveAnswerDraftGeneration());
        $this->assertTrue($question->hasActiveAsyncPipeline());
        $this->assertTrue(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());

        $question->answerDraft->update([
            'status' => AnswerDraftStatus::Draft,
        ]);

        $this->assertFalse($question->refresh()->hasActiveAnswerDraftGeneration());
        $this->assertFalse($question->hasActiveAsyncPipeline());
        $this->assertFalse(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());
    }

    public function test_queued_answer_draft_attributes_clear_stale_generated_content(): void
    {
        $this->assertSame([
            'generated_answer' => EmailQuestionAnswerDraft::PendingGeneratedAnswer,
            'final_answer' => null,
            'semantic_similarity_score' => null,
            'status' => AnswerDraftStatus::Queued,
            'generation_reason' => null,
            'generation_metadata' => null,
            'generation_error' => null,
            'generation_started_at' => null,
            'generated_at' => null,
            'generation_failed_at' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ], EmailQuestionAnswerDraft::queuedAttributes());
    }
}
