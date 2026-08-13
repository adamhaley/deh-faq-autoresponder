<?php

namespace Database\Factories;

use App\Models\EmailQuestion;
use App\Models\GmailMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailQuestion>
 */
class EmailQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gmail_message_id' => GmailMessage::factory(),
            'question_order' => 1,
            'question_text' => fake()->sentence().'?',
            'normalized_question' => null,
            'question_hash' => hash('sha256', fake()->unique()->sentence()),
            'classification' => null,
            'classification_confidence' => null,
            'classification_reason' => null,
            'review_status' => EmailQuestion::ReviewStatusPendingReview,
            'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusNotStarted,
            'faq_retrieval_error' => null,
            'faq_retrieval_started_at' => null,
            'faq_retrieval_completed_at' => null,
            'faq_retrieval_failed_at' => null,
            'parser_version' => 'n8n-chat-v1',
            'extraction_metadata' => ['source' => 'factory'],
            'classification_metadata' => null,
            'classified_at' => null,
            'reviewed_at' => null,
        ];
    }

    public function classifiedAsValid(): static
    {
        return $this->state(fn (): array => [
            'classification' => EmailQuestion::ClassificationValidFaqQuestion,
            'classification_confidence' => 92,
            'classification_reason' => 'This is a customer FAQ question.',
            'classified_at' => now(),
        ]);
    }

    public function reviewedAs(string $status): static
    {
        return $this->state(fn (): array => [
            'review_status' => $status,
            'reviewed_by_user_id' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
