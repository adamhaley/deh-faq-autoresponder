<?php

namespace App\Services\EmailQuestions;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Embeddings;

class EmailQuestionFaqRetrievalService
{
    private const DefaultMatchCount = 5;

    public function retrieveForReviewedValidQuestions(int $limit = 50): int
    {
        $retrievedQuestions = 0;

        EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusValid)
            ->whereNotNull('reviewed_at')
            ->whereDoesntHave('faqMatches')
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (EmailQuestion $question) use (&$retrievedQuestions): void {
                $this->retrieve($question);
                $retrievedQuestions++;
            });

        return $retrievedQuestions;
    }

    /**
     * @return Collection<int, EmailQuestionFaqMatch>
     */
    public function retrieve(EmailQuestion $question, int $matchCount = self::DefaultMatchCount): Collection
    {
        $queryText = $this->queryText($question);

        if ($queryText === '') {
            $question->faqMatches()->delete();

            return collect();
        }

        $embeddingModel = (string) config('services.openai.embedding_model', 'text-embedding-3-small');
        $embedding = Embeddings::for([$queryText])
            ->dimensions(1536)
            ->cache()
            ->generate(model: $embeddingModel)
            ->first();

        $matches = $this->matchesForEmbedding($embedding, $matchCount);
        $retrievedAt = now();

        DB::transaction(function () use ($question, $matches, $retrievedAt, $embeddingModel, $queryText, $matchCount): void {
            $question->faqMatches()->delete();

            $matches->values()->each(function (FaqEntry $faqEntry, int $index) use ($question, $retrievedAt, $embeddingModel, $queryText, $matchCount): void {
                $distance = (float) $faqEntry->getAttribute('distance');

                $question->faqMatches()->create([
                    'faq_entry_id' => $faqEntry->id,
                    'rank' => $index + 1,
                    'similarity' => 1 - $distance,
                    'distance' => $distance,
                    'retrieval_metadata' => [
                        'embedding_model' => $embeddingModel,
                        'match_count' => $matchCount,
                        'query_text' => $queryText,
                        'source' => self::class,
                    ],
                    'retrieved_at' => $retrievedAt,
                ]);
            });
        });

        return $question
            ->faqMatches()
            ->with(['faqEntry.approvedResponse'])
            ->get();
    }

    /**
     * @param  array<int, float>  $embedding
     * @return Collection<int, FaqEntry>
     */
    private function matchesForEmbedding(array $embedding, int $matchCount): Collection
    {
        return FaqEntry::query()
            ->select(['id', 'question', 'answer'])
            ->selectVectorDistance('embedding', $embedding, 'distance')
            ->whereNotNull('embedding')
            ->orderByVectorDistance('embedding', $embedding)
            ->with('approvedResponse')
            ->limit($matchCount)
            ->get();
    }

    private function queryText(EmailQuestion $question): string
    {
        return trim($question->normalized_question ?: $question->question_text);
    }
}
