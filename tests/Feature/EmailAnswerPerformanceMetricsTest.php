<?php

namespace Tests\Feature;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqEntry;
use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailAnswerPerformanceMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_recent_wording_similarity_oldest_first_labelled_by_review_date(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $this->createReviewedDraft('same answer', 'same answer', null, '2026-08-12 10:00:00');
        $this->createReviewedDraft('abc', 'xyz', null, '2026-08-10 10:00:00');

        $recent = app(EmailAnswerPerformanceMetrics::class)->recentWordingSimilarity();

        $this->assertSame(['Aug 10', 'Aug 12'], $recent['labels']);
        $this->assertSame([0, 100], $recent['scores']);
    }

    public function test_it_averages_the_trend_over_the_last_ten_reviews(): void
    {
        $this->createReviewedDraft('abc', 'xyz', null, '2026-08-01 10:00:00');

        foreach (range(2, 11) as $day) {
            $this->createReviewedDraft('same answer', 'same answer', null, sprintf('2026-08-%02d 10:00:00', $day));
        }

        $recent = app(EmailAnswerPerformanceMetrics::class)->recentWordingSimilarity();

        $this->assertSame([0, 50, 67, 75, 80, 83, 86, 88, 89, 90, 100], $recent['trend']);
    }

    public function test_it_only_keeps_the_most_recent_hundred_reviews(): void
    {
        $this->createReviewedDraft('abc', 'xyz', null, '2026-01-01 10:00:00');

        foreach (range(1, EmailAnswerPerformanceMetrics::RecentReviews) as $minute) {
            $this->createReviewedDraft('same answer', 'same answer', null, Carbon::parse('2026-08-01 10:00:00')->addMinutes($minute));
        }

        $recent = app(EmailAnswerPerformanceMetrics::class)->recentWordingSimilarity();

        $this->assertSame(array_fill(0, EmailAnswerPerformanceMetrics::RecentReviews, 100), $recent['scores']);
    }

    public function test_it_does_not_drift_when_nothing_has_been_reviewed_for_a_long_time(): void
    {
        $this->createReviewedDraft('same answer', 'same answer', null, '2026-08-01 10:00:00');
        $metrics = app(EmailAnswerPerformanceMetrics::class);
        $beforeTheQuietStretch = $metrics->recentWordingSimilarity();

        Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00'));
        $afterTheQuietStretch = $metrics->recentWordingSimilarity();
        Carbon::setTestNow();

        $this->assertSame($beforeTheQuietStretch, $afterTheQuietStretch);
    }

    public function test_it_ignores_drafts_that_were_not_approved(): void
    {
        $this->createReviewedDraft('same answer', 'same answer', null, '2026-08-01 10:00:00', EmailQuestionAnswerDraft::StatusDraft);

        $recent = app(EmailAnswerPerformanceMetrics::class)->recentWordingSimilarity();

        $this->assertSame([], $recent['scores']);
    }

    public function test_it_labels_reviews_in_the_display_timezone(): void
    {
        config(['app.display_timezone' => 'Pacific/Auckland']);
        $this->createReviewedDraft('same answer', 'same answer', null, '2026-08-13 12:00:00');

        $recent = app(EmailAnswerPerformanceMetrics::class)->recentWordingSimilarity();

        $this->assertSame(['Aug 14'], $recent['labels']);
    }

    public function test_it_lists_recent_semantic_similarity_without_unscored_drafts(): void
    {
        $this->createReviewedDraft('same answer', 'same answer', 90, '2026-08-01 10:00:00');
        $this->createReviewedDraft('same answer', 'same answer', null, '2026-08-02 10:00:00');
        $this->createReviewedDraft('same answer', 'same answer', 70, '2026-08-03 10:00:00');

        $recent = app(EmailAnswerPerformanceMetrics::class)->recentSemanticSimilarity();

        $this->assertSame([90, 70], $recent['scores']);
    }

    public function test_it_normalises_markup_before_scoring_wording_similarity(): void
    {
        $metrics = app(EmailAnswerPerformanceMetrics::class);

        $this->assertSame(100, $metrics->answerSimilarityScore('<p>Same&nbsp;answer</p>', 'Same answer'));
        $this->assertNull($metrics->answerSimilarityScore('', 'Final answer'));
    }

    public function test_it_buckets_faq_repetition_distribution_by_times_asked(): void
    {
        $faqAskedOnce = FaqEntry::factory()->create();
        $faqAskedTwice = FaqEntry::factory()->create();
        $faqAskedThreeTimes = FaqEntry::factory()->create();

        $this->createApprovedDraftForFaq($faqAskedOnce);

        $this->createApprovedDraftForFaq($faqAskedTwice);
        $this->createApprovedDraftForFaq($faqAskedTwice);

        $this->createApprovedDraftForFaq($faqAskedThreeTimes);
        $this->createApprovedDraftForFaq($faqAskedThreeTimes);
        $this->createApprovedDraftForFaq($faqAskedThreeTimes);

        $distribution = app(EmailAnswerPerformanceMetrics::class)->faqRepetitionDistribution();

        $this->assertSame(['Asked once', 'Asked twice', 'Asked 3+ times'], $distribution['labels']);
        $this->assertSame([1, 1, 1], $distribution['counts']);
    }

    public function test_it_splits_weekly_counts_into_cold_and_warm_repeats(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00'));

        $faqAskedTwice = FaqEntry::factory()->create();
        $faqAskedOnceInWeekOne = FaqEntry::factory()->create();
        $faqAskedOnceInWeekTwo = FaqEntry::factory()->create();

        // Week of Aug 3: two first-time (cold) approvals.
        $this->createApprovedDraftForFaq($faqAskedTwice, Carbon::parse('2026-08-04 10:00:00'));
        $this->createApprovedDraftForFaq($faqAskedOnceInWeekOne, Carbon::parse('2026-08-05 10:00:00'));

        // Week of Aug 10: one repeat (warm) approval, one new (cold) one.
        $this->createApprovedDraftForFaq($faqAskedTwice, Carbon::parse('2026-08-11 10:00:00'));
        $this->createApprovedDraftForFaq($faqAskedOnceInWeekTwo, Carbon::parse('2026-08-12 10:00:00'));

        $weekly = app(EmailAnswerPerformanceMetrics::class)->weeklyRepetitionCounts(2);

        $this->assertSame(['Aug 3', 'Aug 10'], $weekly['labels']);
        $this->assertSame([2, 1], $weekly['cold_counts']);
        $this->assertSame([0, 1], $weekly['warm_counts']);

        Carbon::setTestNow();
    }

    public function test_it_tracks_cumulative_faq_coverage_and_warm_share(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00'));

        $faqA = FaqEntry::factory()->create();
        $faqB = FaqEntry::factory()->create();

        $this->createApprovedDraftForFaq($faqA, Carbon::parse('2026-08-11 10:00:00'));
        $this->createApprovedDraftForFaq($faqB, Carbon::parse('2026-08-12 10:00:00'));
        $this->createApprovedDraftForFaq($faqA, Carbon::parse('2026-08-13 10:00:00'));

        $metrics = app(EmailAnswerPerformanceMetrics::class)->cumulativeFaqMetrics(3);

        $this->assertSame(['Aug 11', 'Aug 12', 'Aug 13'], $metrics['labels']);
        $this->assertSame([1, 2, 2], $metrics['cumulative_faq_coverage']);
        $this->assertSame([0, 0, 33], $metrics['cumulative_warm_share']);

        Carbon::setTestNow();
    }

    private function createApprovedDraftForFaq(FaqEntry $faqEntry, ?Carbon $reviewedAt = null): void
    {
        $question = EmailQuestion::factory()->create();

        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
            'faq_entry_id' => $faqEntry->id,
            'rank' => 1,
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'reviewed_at' => $reviewedAt ?? now(),
        ]);
    }

    private function createReviewedDraft(
        string $generated,
        string $final,
        ?int $semanticScore,
        string|Carbon $reviewedAt,
        string $status = EmailQuestionAnswerDraft::StatusApproved,
    ): EmailQuestionAnswerDraft {
        return EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => $generated,
            'final_answer' => $final,
            'semantic_similarity_score' => $semanticScore,
            'status' => $status,
            'reviewed_at' => $reviewedAt,
        ]);
    }
}
