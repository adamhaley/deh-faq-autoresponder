<?php

namespace Tests\Feature;

use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionReviewStatus;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailThreadDraft;
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
            'review_status' => EmailQuestionReviewStatus::PendingReview,
        ]);

        $this->assertTrue($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_does_not_need_review_once_all_questions_are_noise_or_unanswerable(): void
    {
        $message = GmailMessage::factory()->create();
        EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestionReviewStatus::Noise)->create();
        EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestionReviewStatus::Unanswerable)->create();

        $this->assertFalse($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_needs_review_while_a_valid_question_has_no_terminal_answer(): void
    {
        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestionReviewStatus::Valid)->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => AnswerDraftStatus::Draft,
        ]);

        $this->assertTrue($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_does_not_need_review_once_a_valid_question_is_approved(): void
    {
        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestionReviewStatus::Valid)->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => AnswerDraftStatus::Approved,
        ]);

        $this->assertFalse($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_does_not_need_review_once_a_valid_question_is_rejected(): void
    {
        $message = GmailMessage::factory()->create();
        $question = EmailQuestion::factory()->for($message, 'message')->reviewedAs(EmailQuestionReviewStatus::Valid)->create();
        EmailQuestionAnswerDraft::factory()->create([
            'email_question_id' => $question->id,
            'status' => AnswerDraftStatus::Rejected,
        ]);

        $this->assertFalse($message->load('questions.answerDraft')->needsReview());
    }

    public function test_a_message_has_no_composed_draft_when_none_exists(): void
    {
        $message = GmailMessage::factory()->create();

        $this->assertFalse($message->load('threadDraft')->hasComposedDraft());
    }

    public function test_a_message_has_a_composed_draft_once_its_thread_is_composed(): void
    {
        $message = GmailMessage::factory()->create(['thread_id' => 'thread-composed']);
        EmailThreadDraft::factory()->create(['thread_id' => 'thread-composed']);

        $this->assertTrue($message->load('threadDraft')->hasComposedDraft());
    }
}
