<?php

namespace Tests\Feature;

use App\Jobs\ComposeEmailThreadDraft;
use App\Jobs\ScoreAnswerSemanticSimilarity;
use App\Models\EmailQuestionAnswerDraft;
use App\Services\EmailQuestions\AnswerSemanticSimilarityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class ScoreAnswerSemanticSimilarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_semantic_similarity_score_for_an_approved_draft(): void
    {
        $vector = array_pad([1.0, 0.0], 1536, 0.0);
        Embeddings::fake([[$vector, $vector]]);

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Original AI draft.',
            'final_answer' => 'Original AI draft.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
        ]);

        (new ScoreAnswerSemanticSimilarity($draft->id))->handle(app(AnswerSemanticSimilarityScorer::class));

        $this->assertSame(100, $draft->fresh()->semantic_similarity_score);
    }

    public function test_it_does_nothing_for_a_non_approved_draft(): void
    {
        $draft = EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Draft answer.',
            'final_answer' => 'Draft answer.',
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        (new ScoreAnswerSemanticSimilarity($draft->id))->handle(app(AnswerSemanticSimilarityScorer::class));

        $this->assertNull($draft->fresh()->semantic_similarity_score);
    }

    public function test_approving_a_draft_dispatches_the_scoring_job(): void
    {
        Queue::fake();

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'final_answer' => 'Answer.',
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        $draft->markReviewed(EmailQuestionAnswerDraft::StatusApproved, reviewerId: null);

        Queue::assertPushed(
            ScoreAnswerSemanticSimilarity::class,
            fn (ScoreAnswerSemanticSimilarity $job): bool => $job->draftId === $draft->id,
        );
    }

    public function test_the_scoring_job_does_not_redispatch_thread_draft_composition(): void
    {
        $vector = array_pad([1.0, 0.0], 1536, 0.0);
        Embeddings::fake([[$vector, $vector]]);
        Queue::fake([ComposeEmailThreadDraft::class]);

        $draft = EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Original AI draft.',
            'final_answer' => 'Original AI draft.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
        ]);

        (new ScoreAnswerSemanticSimilarity($draft->id))->handle(app(AnswerSemanticSimilarityScorer::class));

        Queue::assertNotPushed(ComposeEmailThreadDraft::class);
    }
}
