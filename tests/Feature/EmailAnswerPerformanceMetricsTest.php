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

    public function test_it_calculates_daily_generated_to_approved_answer_similarity(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00'));

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'identical answer',
            'final_answer' => 'identical answer',
            'semantic_similarity_score' => 100,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'reviewed_at' => now()->subDays(2),
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'same answer',
            'final_answer' => 'same answer',
            'semantic_similarity_score' => 90,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'reviewed_at' => now()->subDay(),
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'abc',
            'final_answer' => 'xyz',
            'semantic_similarity_score' => null,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
            'reviewed_at' => now()->subDay(),
        ]);

        EmailQuestionAnswerDraft::factory()->create([
            'generated_answer' => 'ignored draft',
            'final_answer' => 'ignored final',
            'status' => EmailQuestionAnswerDraft::StatusDraft,
            'reviewed_at' => now()->subDay(),
        ]);

        $metrics = app(EmailAnswerPerformanceMetrics::class);
        $daily = $metrics->dailySimilarityScores(3);

        $this->assertSame(['Aug 11', 'Aug 12', 'Aug 13'], $daily['labels']);
        $this->assertSame([100, 50, null], $daily['similarity_scores']);
        $this->assertSame([100, 90, null], $daily['semantic_similarity_scores']);
        $this->assertSame([1, 2, 0], $daily['approved_counts']);
        $this->assertSame(100, $metrics->answerSimilarityScore('<p>Same&nbsp;answer</p>', 'Same answer'));
        $this->assertNull($metrics->answerSimilarityScore('', 'Final answer'));

        Carbon::setTestNow();
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
}
