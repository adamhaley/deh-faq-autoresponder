<?php

namespace Tests\Feature;

use App\Services\EmailQuestions\AnswerSemanticSimilarityScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Tests\TestCase;

class AnswerSemanticSimilarityScorerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_identical_embeddings_as_100(): void
    {
        $vector = $this->embedding([1.0, 0.0]);
        Embeddings::fake([[$vector, $vector]]);

        $score = app(AnswerSemanticSimilarityScorer::class)->score('generated', 'final');

        $this->assertSame(100, $score);
    }

    public function test_it_scores_orthogonal_embeddings_as_0(): void
    {
        Embeddings::fake([[$this->embedding([1.0, 0.0]), $this->embedding([0.0, 1.0])]]);

        $score = app(AnswerSemanticSimilarityScorer::class)->score('generated', 'final');

        $this->assertSame(0, $score);
    }

    public function test_it_returns_null_when_either_answer_is_empty(): void
    {
        $scorer = app(AnswerSemanticSimilarityScorer::class);

        $this->assertNull($scorer->score('', 'final'));
        $this->assertNull($scorer->score('generated', ''));
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
