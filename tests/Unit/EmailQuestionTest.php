<?php

namespace Tests\Unit;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use Tests\TestCase;

class EmailQuestionTest extends TestCase
{
    public function test_human_review_decision_options_only_include_reviewer_decisions(): void
    {
        $this->assertSame([
            EmailQuestion::ReviewStatusValid => 'Valid question',
            EmailQuestion::ReviewStatusNoise => 'Noise',
            EmailQuestion::ReviewStatusUnanswerable => 'Unanswerable',
        ], EmailQuestion::humanReviewDecisionOptions());
    }

    public function test_human_review_filter_options_include_pending_but_not_needs_human(): void
    {
        $this->assertSame([
            EmailQuestion::ReviewStatusPendingReview => 'Pending review',
            EmailQuestion::ReviewStatusValid => 'Valid question',
            EmailQuestion::ReviewStatusNoise => 'Noise',
            EmailQuestion::ReviewStatusUnanswerable => 'Unanswerable',
        ], EmailQuestion::humanReviewFilterOptions());
    }

    public function test_active_async_pipeline_detects_faq_retrieval_work(): void
    {
        $question = EmailQuestion::factory()->create([
            'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusQueued,
        ]);

        $this->assertTrue($question->hasActiveFaqRetrieval());
        $this->assertTrue($question->hasActiveAsyncPipeline());
        $this->assertTrue(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());

        $question->update([
            'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusCompleted,
        ]);

        $this->assertFalse($question->refresh()->hasActiveFaqRetrieval());
        $this->assertFalse($question->hasActiveAsyncPipeline());
        $this->assertFalse(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());
    }

    public function test_active_async_pipeline_detects_answer_draft_generation_work(): void
    {
        $question = EmailQuestion::factory()
            ->has(EmailQuestionAnswerDraft::factory()->state([
                'status' => EmailQuestionAnswerDraft::StatusGenerating,
            ]), 'answerDraft')
            ->create();

        $this->assertTrue($question->refresh()->hasActiveAnswerDraftGeneration());
        $this->assertTrue($question->hasActiveAsyncPipeline());
        $this->assertTrue(EmailQuestion::query()->withActiveAsyncPipeline()->whereKey($question)->exists());

        $question->answerDraft->update([
            'status' => EmailQuestionAnswerDraft::StatusDraft,
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
            'status' => EmailQuestionAnswerDraft::StatusQueued,
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
