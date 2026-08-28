<?php

namespace Tests\Feature;

use App\Models\EmailQuestionAnswerDraft;
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
}
