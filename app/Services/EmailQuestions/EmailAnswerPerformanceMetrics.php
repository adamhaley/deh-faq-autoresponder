<?php

namespace App\Services\EmailQuestions;

use App\Models\EmailQuestionAnswerDraft;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
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
            ->where('status', EmailQuestionAnswerDraft::StatusApproved)
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

    /**
     * How many distinct FAQ entries have been asked exactly once, twice, or
     * 3+ times, counting each approved draft against its single best-ranked
     * FAQ match. Answers "is this actually repeating, and how much" -
     * distinct from dailySimilarityScores(), which tracks answer quality,
     * not question repetition.
     *
     * @return array{labels: list<string>, counts: list<int>}
     */
    public function faqRepetitionDistribution(): array
    {
        $timesAskedPerFaq = $this->approvedDraftsWithBestMatch()
            ->countBy(fn (array $draft): string => $draft['faq_entry_id']);

        $buckets = ['1' => 0, '2' => 0, '3+' => 0];

        foreach ($timesAskedPerFaq as $timesAsked) {
            $key = match (true) {
                $timesAsked === 1 => '1',
                $timesAsked === 2 => '2',
                default => '3+',
            };

            $buckets[$key]++;
        }

        return [
            'labels' => [
                __('admin.dashboard.faq_repetition_once'),
                __('admin.dashboard.faq_repetition_twice'),
                __('admin.dashboard.faq_repetition_three_plus'),
            ],
            'counts' => array_values($buckets),
        ];
    }

    /**
     * Weekly count of approved drafts whose best-matched FAQ had never been
     * approved before ("cold") vs. already had at least one prior approval
     * ("warm"). "First time ever" is judged across the drafts' full history,
     * not just the requested window, so a FAQ approved once before the
     * window starts still counts as warm inside it.
     *
     * @return array{labels: list<string>, cold_counts: list<int>, warm_counts: list<int>}
     */
    public function weeklyRepetitionCounts(int $weeks): array
    {
        $start = now()->subWeeks($weeks - 1)->startOfWeek();
        $end = now()->endOfWeek();

        $drafts = $this->approvedDraftsWithBestMatch()->sortBy('reviewed_at');

        $seenFaqEntryIds = [];
        $coldByWeek = [];
        $warmByWeek = [];

        foreach ($drafts as $draft) {
            $isWarm = in_array($draft['faq_entry_id'], $seenFaqEntryIds, true);
            $seenFaqEntryIds[] = $draft['faq_entry_id'];

            if ($draft['reviewed_at']->lt($start) || $draft['reviewed_at']->gt($end)) {
                continue;
            }

            $week = $draft['reviewed_at']->clone()->startOfWeek()->toDateString();

            if ($isWarm) {
                $warmByWeek[$week] = ($warmByWeek[$week] ?? 0) + 1;
            } else {
                $coldByWeek[$week] = ($coldByWeek[$week] ?? 0) + 1;
            }
        }

        $labels = [];
        $coldCounts = [];
        $warmCounts = [];

        foreach (CarbonPeriod::create($start, '1 week', $end) as $weekStart) {
            $week = $weekStart->toDateString();

            $labels[] = $weekStart->format('M j');
            $coldCounts[] = $coldByWeek[$week] ?? 0;
            $warmCounts[] = $warmByWeek[$week] ?? 0;
        }

        return [
            'labels' => $labels,
            'cold_counts' => $coldCounts,
            'warm_counts' => $warmCounts,
        ];
    }

    /**
     * Day by day: cumulative count of distinct FAQ entries that have
     * received at least one approval, and cumulative warm share (running
     * warm approvals / running total approvals, as a percentage). Computed
     * together since both walk the same sorted timeline once.
     *
     * A plain running total of approvals would only ever go up and
     * wouldn't say anything on its own. These two are meaningfully
     * different: FAQ coverage should climb steeply while new topics keep
     * showing up, then bend flat once the frequently-asked set is mostly
     * discovered -- the bend itself is the signal. Warm share is bounded
     * 0-100%, so its trend actually means something: rising toward a
     * plateau says the feedback loop is covering a growing share of
     * traffic, staying flat near 0 says it isn't.
     *
     * @return array{labels: list<string>, cumulative_faq_coverage: list<int>, cumulative_warm_share: list<int|null>}
     */
    public function cumulativeFaqMetrics(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();

        $drafts = $this->approvedDraftsWithBestMatch()->sortBy('reviewed_at')->values();

        $seenFaqEntryIds = [];
        $cumulativeWarm = 0;
        $cumulativeTotal = 0;
        $draftIndex = 0;

        $labels = [];
        $coverage = [];
        $warmShare = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $dayEnd = $date->clone()->endOfDay();

            while ($draftIndex < $drafts->count() && $drafts[$draftIndex]['reviewed_at']->lte($dayEnd)) {
                $faqEntryId = $drafts[$draftIndex]['faq_entry_id'];

                if (isset($seenFaqEntryIds[$faqEntryId])) {
                    $cumulativeWarm++;
                }

                $seenFaqEntryIds[$faqEntryId] = true;
                $cumulativeTotal++;
                $draftIndex++;
            }

            $labels[] = $date->format('M j');
            $coverage[] = count($seenFaqEntryIds);
            $warmShare[] = $cumulativeTotal > 0 ? (int) round($cumulativeWarm / $cumulativeTotal * 100) : null;
        }

        return [
            'labels' => $labels,
            'cumulative_faq_coverage' => $coverage,
            'cumulative_warm_share' => $warmShare,
        ];
    }

    /**
     * Each approved, reviewed draft paired with its single best-ranked FAQ
     * match, oldest first. Drafts with no FAQ match (shouldn't normally
     * happen for an approved draft, but not guaranteed by a DB constraint)
     * are excluded.
     *
     * @return Collection<int, array{faq_entry_id: string, reviewed_at: Carbon}>
     */
    private function approvedDraftsWithBestMatch(): Collection
    {
        return EmailQuestionAnswerDraft::query()
            ->where('status', EmailQuestionAnswerDraft::StatusApproved)
            ->whereNotNull('reviewed_at')
            ->with(['emailQuestion.faqMatches' => fn ($query) => $query->where('rank', 1)])
            ->get(['id', 'email_question_id', 'reviewed_at'])
            ->map(function (EmailQuestionAnswerDraft $draft): ?array {
                $faqEntryId = $draft->emailQuestion?->faqMatches->first()?->faq_entry_id;

                return $faqEntryId === null ? null : [
                    'faq_entry_id' => $faqEntryId,
                    'reviewed_at' => $draft->reviewed_at,
                ];
            })
            ->filter()
            ->values();
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
