<?php

namespace App\Services\EmailQuestions;

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

class AnswerSemanticSimilarityScorer
{
    /**
     * Embedding-cosine similarity between an AI-generated draft answer and
     * the reviewer's final answer, as a 0-100 score comparable to the
     * literal similar_text() score used elsewhere for the same pair.
     */
    public function score(string $generatedAnswer, string $finalAnswer): ?int
    {
        if ($generatedAnswer === '' || $finalAnswer === '') {
            return null;
        }

        $embeddingModel = (string) config('services.openai.embedding_model', 'text-embedding-3-small');

        [$generatedEmbedding, $finalEmbedding] = Embeddings::for([$generatedAnswer, $finalAnswer])
            ->dimensions(1536)
            ->generate(model: $embeddingModel)
            ->embeddings;

        $distance = $this->cosineDistance($generatedEmbedding, $finalEmbedding);

        return (int) round(max(0.0, min(1.0, 1 - $distance)) * 100);
    }

    /**
     * Reuses pgvector's native `<=>` cosine-distance operator -- the same
     * one Laravel's selectVectorDistance()/orderByVectorDistance() macros
     * use against a stored column -- rather than reimplementing cosine
     * math in PHP. Neither vector here is a stored column value, so the
     * comparison runs as a standalone expression instead of a table query.
     *
     * @param  array<int, float>  $a
     * @param  array<int, float>  $b
     */
    private function cosineDistance(array $a, array $b): float
    {
        return (float) DB::selectOne(
            'select ?::vector <=> ?::vector as distance',
            [$this->toVectorLiteral($a), $this->toVectorLiteral($b)],
        )->distance;
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function toVectorLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
