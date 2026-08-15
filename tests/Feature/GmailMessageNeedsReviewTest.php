<?php

namespace Tests\Feature;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\GmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GmailMessageNeedsReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_message_with_no_extracted_questions_does_not_need_review(): void
    {
        $message = GmailMessage::factory()->create()->load('questions');

        $this->assertFalse($message->needsReview());
    }

    public function test_a_message_needs_review_while_a_question_is_pending(): void
    {
        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($message, 'message')->create([
            'review_status' => EmailQuestion::ReviewStatusPendingReview,
        ]);

        $this->assertTrue($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_does_not_need_review_once_all_questions_are_noise_or_unanswerable(): void
    {
        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestion::ReviewStatusNoise)->create();
        EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestion::ReviewStatusUnanswerable)->create();

        $this->assertFalse($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_needs_review_while_a_valid_question_has_no_terminal_answer(): void
    {
        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestion::ReviewStatusValid)->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => EmailQuestionAnswerDraft::StatusDraft,
        ]);

        $this->assertTrue($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_does_not_need_review_once_a_valid_question_is_approved(): void
    {
        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestion::ReviewStatusValid)->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => EmailQuestionAnswerDraft::StatusApproved,
        ]);

        $this->assertFalse($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_does_not_need_review_once_a_valid_question_is_rejected(): void
    {
        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestion::ReviewStatusValid)->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => EmailQuestionAnswerDraft::StatusRejected,
        ]);

        $this->assertFalse($message->load('questions.answerDraft')->needsReview());
    }
}
