<?php

namespace Tests\Feature;

use App\Jobs\ScoreAnswerSemanticSimilarity;
use App\Models\EmailQuestionAnswerDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackfillAnswerSemanticSimilarityScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_queues_only_approved_drafts_missing_a_score(): void
    {
        Queue::fake();

        $missingScore = EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'semantic_similarity_score' => null,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'semantic_similarity_score' => 82,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => EmailQuestionAnswerDraft::StatusDraft,
            'semantic_similarity_score' => null,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => null,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'semantic_similarity_score' => null,
        ]);

        $this->artisan('email-questions:backfill-semantic-similarity')
            ->expectsOutput('Queued semantic-similarity scoring for 1 approved answer draft(s).')
            ->assertSuccessful();

        Queue::assertPushed(
            ScoreAnswerSemanticSimilarity::class,
            fn (ScoreAnswerSemanticSimilarity $job): bool => $job->draftId === $missingScore->id,
        );
        Queue::assertPushed(ScoreAnswerSemanticSimilarity::class, 1);
    }

    public function test_command_respects_the_limit_option(): void
    {
        Queue::fake();

        EmailQuestionAnswerDraft::factory()->count(3)->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'semantic_similarity_score' => null,
        ]);

        $this->artisan('email-questions:backfill-semantic-similarity', ['--limit' => 2])
            ->expectsOutput('Queued semantic-similarity scoring for 2 approved answer draft(s).')
            ->assertSuccessful();

        Queue::assertPushed(ScoreAnswerSemanticSimilarity::class, 2);
    }
}
