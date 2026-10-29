<?php

namespace Tests\Feature;

use App\Models\EmailQuestion;
use App\Services\EmailQuestions\EmailQuestionDashboardMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmailQuestionDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_reviewed_alignment_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00'));

        EmailQuestion::factory()->create([
            'review_status' => EmailQuestion::ReviewStatusPendingReview,
        ]);

        EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'classification' => EmailQuestion::ClassificationValidFaqQuestion,
                'reviewed_at' => now()->subDays(2),
            ]);

        EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusNoise)
            ->create([
                'classification' => EmailQuestion::ClassificationNoise,
                'reviewed_at' => now()->subDay(),
            ]);

        $misalignment = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Can this inherited gemstone be valued?',
                'classification' => EmailQuestion::ClassificationNoise,
                'reviewed_at' => now()->subDay(),
            ]);

        EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'classification' => EmailQuestion::ClassificationValidFaqQuestion,
                'reviewed_at' => now()->subDays(10),
            ]);

        $metrics = app(EmailQuestionDashboardMetrics::class);

        $this->assertSame(1, $metrics->pendingReviewCount());
        $this->assertSame(3, $metrics->reviewedSince(now()->subDays(7)));
        $this->assertSame(67, $metrics->alignmentRateSince(now()->subDays(7)));
        $this->assertSame(1, $metrics->misalignmentCountSince(now()->subDays(7)));

        $this->assertSame(
            [$misalignment->id],
            $metrics->recentMisalignmentsQuery()->pluck('id')->all(),
        );

        Carbon::setTestNow();
    }

    public function test_it_lists_the_rolling_misalignment_rate_in_review_order(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $this->createReviewedQuestion(aligned: true, reviewedAt: '2026-08-10 10:00:00');
        $this->createReviewedQuestion(aligned: false, reviewedAt: '2026-08-11 10:00:00');
        $this->createReviewedQuestion(aligned: true, reviewedAt: '2026-08-12 10:00:00');

        $recent = app(EmailQuestionDashboardMetrics::class)->recentMisalignmentRates();

        $this->assertSame(['Aug 10', 'Aug 11', 'Aug 12'], $recent['labels']);
        $this->assertSame([0, 50, 33], $recent['misalignment_rates']);
    }

    public function test_it_only_averages_the_last_trend_window_of_reviews(): void
    {
        $this->createReviewedQuestion(aligned: false, reviewedAt: '2026-08-01 10:00:00');

        foreach (range(1, EmailQuestionDashboardMetrics::TrendWindow) as $minute) {
            $this->createReviewedQuestion(aligned: true, reviewedAt: Carbon::parse('2026-08-02 10:00:00')->addMinutes($minute));
        }

        $recent = app(EmailQuestionDashboardMetrics::class)->recentMisalignmentRates();

        $this->assertSame(0, end($recent['misalignment_rates']));
    }

    public function test_it_drops_the_warm_up_points_once_a_full_window_exists(): void
    {
        foreach (range(1, EmailQuestionDashboardMetrics::TrendWindow + 2) as $minute) {
            $this->createReviewedQuestion(aligned: true, reviewedAt: Carbon::parse('2026-08-01 10:00:00')->addMinutes($minute));
        }

        $recent = app(EmailQuestionDashboardMetrics::class)->recentMisalignmentRates();

        $this->assertCount(3, $recent['misalignment_rates']);
    }

    public function test_it_only_keeps_the_most_recent_reviews(): void
    {
        $this->createReviewedQuestion(aligned: false, reviewedAt: '2026-01-01 10:00:00');

        foreach (range(1, EmailQuestionDashboardMetrics::RecentReviews) as $minute) {
            $this->createReviewedQuestion(aligned: true, reviewedAt: Carbon::parse('2026-08-01 10:00:00')->addMinutes($minute));
        }

        $recent = app(EmailQuestionDashboardMetrics::class)->recentMisalignmentRates();

        $this->assertSame(0, max($recent['misalignment_rates']));
    }

    public function test_it_does_not_drift_when_nothing_has_been_reviewed_for_a_long_time(): void
    {
        $this->createReviewedQuestion(aligned: false, reviewedAt: '2026-08-01 10:00:00');
        $metrics = app(EmailQuestionDashboardMetrics::class);
        $beforeTheQuietStretch = $metrics->recentMisalignmentRates();

        Carbon::setTestNow(Carbon::parse('2026-12-01 12:00:00'));
        $afterTheQuietStretch = $metrics->recentMisalignmentRates();
        Carbon::setTestNow();

        $this->assertSame($beforeTheQuietStretch, $afterTheQuietStretch);
    }

    public function test_it_ignores_questions_still_pending_review(): void
    {
        EmailQuestion::factory()->create([
            'review_status' => EmailQuestion::ReviewStatusPendingReview,
            'reviewed_at' => '2026-08-01 10:00:00',
        ]);

        $recent = app(EmailQuestionDashboardMetrics::class)->recentMisalignmentRates();

        $this->assertSame([], $recent['misalignment_rates']);
    }

    private function createReviewedQuestion(bool $aligned, string|Carbon $reviewedAt): EmailQuestion
    {
        return EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'classification' => $aligned
                    ? EmailQuestion::ClassificationValidFaqQuestion
                    : EmailQuestion::ClassificationNoise,
                'reviewed_at' => $reviewedAt,
            ]);
    }
}
