<?php

namespace App\Services\EmailQuestions;

use App\Enums\AnswerDraftStatus;
use App\Models\EmailQuestionAnswerDraft;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class EmailAnswerPerformanceMetrics
{
    /**
     * @return array{labels: list<string>, similarity_scores: list<int|null>, semantic_similarity_scores: list<int|null>, approved_counts: list<int>}
     */
    public function dailySimilarityScores(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();
        $draftsByDay = EmailQuestionAnswerDraft::query()
            ->where('status', AnswerDraftStatus::Approved)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('generated_answer')
            ->whereNotNull('final_answer')
            ->whereBetween('reviewed_at', [$start, $end])
            ->get(['generated_answer', 'final_answer', 'semantic_similarity_score', 'reviewed_at'])
            ->groupBy(fn (EmailQuestionAnswerDraft $draft): string => $draft->reviewed_at?->toDateString() ?? '');

        $labels = [];
        $similarityScores = [];
        $semanticSimilarityScores = [];
        $approvedCounts = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $day = $date->toDateString();
            $drafts = $draftsByDay->get($day, collect());

            $labels[] = $date->format('M j');
            $similarityScores[] = $this->averageSimilarityScore($drafts);
            $semanticSimilarityScores[] = $this->averageSemanticSimilarityScore($drafts);
            $approvedCounts[] = $drafts->count();
        }

        return [
            'labels' => $labels,
            'similarity_scores' => $similarityScores,
            'semantic_similarity_scores' => $semanticSimilarityScores,
            'approved_counts' => $approvedCounts,
        ];
    }

    public function answerSimilarityScore(?string $generatedAnswer, ?string $finalAnswer): ?int
    {
        $generatedAnswer = $this->normalizeAnswer($generatedAnswer);
        $finalAnswer = $this->normalizeAnswer($finalAnswer);

        if ($generatedAnswer === '' || $finalAnswer === '') {
            return null;
        }

        similar_text($generatedAnswer, $finalAnswer, $percent);

        return (int) round($percent);
    }

    /**
     * @param  Collection<int, EmailQuestionAnswerDraft>  $drafts
     */
    private function averageSimilarityScore(Collection $drafts): ?int
    {
        if ($drafts->isEmpty()) {
            return null;
        }

        $scores = $drafts
            ->map(fn (EmailQuestionAnswerDraft $draft): ?int => $this->answerSimilarityScore(
                $draft->generated_answer,
                $draft->final_answer,
            ))
            ->filter(fn (?int $score): bool => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return (int) round($scores->average());
    }

    /**
     * Stored at approval time by ScoreAnswerSemanticSimilarity, not computed
     * live -- unlike answerSimilarityScore(), so drafts approved before this
     * feature shipped have no value here and are excluded, not zeroed.
     *
     * @param  Collection<int, EmailQuestionAnswerDraft>  $drafts
     */
    private function averageSemanticSimilarityScore(Collection $drafts): ?int
    {
        $scores = $drafts
            ->pluck('semantic_similarity_score')
            ->filter(fn (?int $score): bool => $score !== null);

        if ($scores->isEmpty()) {
            return null;
        }

        return (int) round($scores->average());
    }

    private function normalizeAnswer(?string $answer): string
    {
        if ($answer === null) {
            return '';
        }

        $answer = html_entity_decode(strip_tags($answer), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $answer = preg_replace('/\s+/u', ' ', $answer) ?? $answer;

        return trim($answer);
    }
}
