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

        $daily = $metrics->dailyMisalignmentRates(3);

        $this->assertSame(['Aug 11', 'Aug 12', 'Aug 13'], $daily['labels']);
        $this->assertSame([0, 50, null], $daily['misalignment_rates']);
        $this->assertSame([1, 2, 0], $daily['reviewed_counts']);

        $this->assertSame(
            [$misalignment->id],
            $metrics->recentMisalignmentsQuery()->pluck('id')->all(),
        );

        Carbon::setTestNow();
    }
}
