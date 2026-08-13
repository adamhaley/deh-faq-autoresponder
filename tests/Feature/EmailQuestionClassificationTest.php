<?php

namespace Tests\Feature;

use App\Ai\Agents\EmailQuestionClassifier;
use App\Models\EmailQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class EmailQuestionClassificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_classifies_pending_email_questions_with_structured_ai_output(): void
    {
        EmailQuestionClassifier::fake([
            [
                'classification' => EmailQuestion::ClassificationValidFaqQuestion,
                'confidence' => 91,
                'reason' => 'The text asks a clear question about gemstone appraisal.',
                'normalized_question' => 'Wie werden die Wertgutachten für Edelsteine erstellt?',
            ],
        ])->preventStrayPrompts();

        $question = EmailQuestion::factory()->create([
            'question_text' => 'Wie werden Wertgutachten erstellt?',
        ]);

        $this->artisan('email-questions:classify')
            ->expectsOutput('Classified 1 email question(s).')
            ->assertSuccessful();

        $question = $question->fresh();

        $this->assertInstanceOf(EmailQuestion::class, $question);
        $this->assertSame(EmailQuestion::ClassificationValidFaqQuestion, $question->classification);
        $this->assertSame(91, $question->classification_confidence);
        $this->assertSame('The text asks a clear question about gemstone appraisal.', $question->classification_reason);
        $this->assertSame('Wie werden die Wertgutachten für Edelsteine erstellt?', $question->normalized_question);
        $this->assertSame(EmailQuestion::ReviewStatusPendingReview, $question->review_status);
        $this->assertNotNull($question->classified_at);

        EmailQuestionClassifier::assertPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains('Wie werden Wertgutachten erstellt?'),
        );
    }

    public function test_command_skips_already_classified_questions(): void
    {
        EmailQuestionClassifier::fake()->preventStrayPrompts();

        EmailQuestion::factory()->classifiedAsValid()->create();

        $this->artisan('email-questions:classify')
            ->expectsOutput('Classified 0 email question(s).')
            ->assertSuccessful();

        EmailQuestionClassifier::assertNeverPrompted();
    }
}
