<?php

namespace Tests\Feature;

use App\Enums\AnswerDraftStatus;
use App\Models\EmailQuestionAnswerDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class BackfillAnswerSemanticSimilarityScoresTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_scores_only_approved_drafts_missing_a_score(): void
    {
        $vector = $this->embedding([1.0, 0.0]);
        Embeddings::fake([[$vector, $vector]]);

        $missingScore = EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => AnswerDraftStatus::Approved,
            'semantic_similarity_score' => null,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => AnswerDraftStatus::Approved,
            'semantic_similarity_score' => 82,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => AnswerDraftStatus::Draft,
            'semantic_similarity_score' => null,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'Generated.',
            'final_answer' => null,
            'status' => AnswerDraftStatus::Approved,
            'semantic_similarity_score' => null,
        ]);

        $this->artisan('email-questions:backfill-semantic-similarity')
            ->expectsOutput('Scored 1 approved answer draft(s).')
            ->assertSuccessful();

        $this->assertSame(100, $missingScore->fresh()->semantic_similarity_score);
    }

    public function test_command_respects_the_limit_option(): void
    {
        $vector = $this->embedding([1.0, 0.0]);
        Embeddings::fake([[$vector, $vector], [$vector, $vector]]);

        EmailQuestionAnswerDraft::factory()->count(3)->create([
            'generated_answer' => 'Generated.',
            'final_answer' => 'Final.',
            'status' => AnswerDraftStatus::Approved,
            'semantic_similarity_score' => null,
        ]);

        $this->artisan('email-questions:backfill-semantic-similarity', ['--limit' => 2])
            ->expectsOutput('Scored 2 approved answer draft(s).')
            ->assertSuccessful();

        $this->assertSame(2, EmailQuestionAnswerDraft::query()->whereNotNull('semantic_similarity_score')->count());
    }

    /**
     * @param  list<float>  $prefix
     * @return list<float>
     */
    private function embedding(array $prefix): array
    {
        return array_pad($prefix, 1536, 0.0);
    }
}
