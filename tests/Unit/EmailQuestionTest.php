<?php

namespace Tests\Unit;

use App\Models\EmailQuestion;
use Tests\TestCase;

class EmailQuestionTest extends TestCase
{
    public function test_human_review_decision_options_only_include_reviewer_decisions(): void
    {
        $this->assertSame([
            EmailQuestion::ReviewStatusValid => 'Valid question',
            EmailQuestion::ReviewStatusNoise => 'Noise',
            EmailQuestion::ReviewStatusUnanswerable => 'Unanswerable',
        ], EmailQuestion::humanReviewDecisionOptions());
    }

    public function test_human_review_filter_options_include_pending_but_not_needs_human(): void
    {
        $this->assertSame([
            EmailQuestion::ReviewStatusPendingReview => 'Pending review',
            EmailQuestion::ReviewStatusValid => 'Valid question',
            EmailQuestion::ReviewStatusNoise => 'Noise',
            EmailQuestion::ReviewStatusUnanswerable => 'Unanswerable',
        ], EmailQuestion::humanReviewFilterOptions());
    }
}
