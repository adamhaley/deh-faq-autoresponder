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
        $this->assertSame([], $question->classification_metadata['training_example_ids']);
        $this->assertNotNull($question->classified_at);

        EmailQuestionClassifier::assertPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains('Wie werden Wertgutachten erstellt?')
                && $prompt->contains('No human-reviewed examples are available yet.'),
        );
    }

    public function test_command_includes_human_reviewed_examples_in_classifier_prompt(): void
    {
        EmailQuestionClassifier::fake([
            [
                'classification' => EmailQuestion::ClassificationValidFaqQuestion,
                'confidence' => 88,
                'reason' => 'The text asks about whether an asset can be evaluated.',
                'normalized_question' => 'Kann dieser Edelstein bewertet werden?',
            ],
        ])->preventStrayPrompts();

        $validExample = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Können geerbte Edelsteine bewertet werden?',
                'classification' => EmailQuestion::ClassificationNoise,
                'classification_confidence' => 74,
                'classification_reason' => 'Originally considered noise.',
                'classified_at' => now(),
            ]);

        $noiseExample = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusNoise)
            ->create([
                'question_text' => 'hören ja, und 2 fotos',
                'classification' => EmailQuestion::ClassificationNoise,
                'classification_confidence' => 95,
                'classification_reason' => 'Fragmentary chat text.',
                'classified_at' => now(),
            ]);

        $unreviewedQuestion = EmailQuestion::factory()->create([
            'question_text' => 'This unreviewed row must not become training data.',
            'classified_at' => now(),
        ]);

        $question = EmailQuestion::factory()->create([
            'question_text' => 'Kann dieser Stein bewertet werden?',
        ]);

        $this->artisan('email-questions:classify')
            ->expectsOutput('Classified 1 email question(s).')
            ->assertSuccessful();

        $question = $question->fresh();

        $this->assertInstanceOf(EmailQuestion::class, $question);
        $this->assertEqualsCanonicalizing(
            [$validExample->id, $noiseExample->id],
            $question->classification_metadata['training_example_ids'],
        );

        EmailQuestionClassifier::assertPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains('Human-reviewed examples:')
                && $prompt->contains('Können geerbte Edelsteine bewertet werden?')
                && $prompt->contains('Correct human classification: Valid question')
                && $prompt->contains('Original AI classification: Noise')
                && $prompt->contains('hören ja, und 2 fotos')
                && ! $prompt->contains($unreviewedQuestion->question_text),
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
