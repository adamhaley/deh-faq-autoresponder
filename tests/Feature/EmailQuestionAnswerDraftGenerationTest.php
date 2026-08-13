<?php

namespace Tests\Feature;

use App\Ai\Agents\EmailQuestionAnswerGenerator;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqApprovedResponse;
use App\Models\FaqEntry;
use App\Services\EmailQuestions\EmailQuestionAnswerDraftGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\TestCase;

class EmailQuestionAnswerDraftGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_and_persists_answer_draft_from_retrieved_faq_matches(): void
    {
        EmailQuestionAnswerGenerator::fake([
            [
                'answer' => 'Ja, Aktien können im Rahmen der Umwandlung besprochen werden.',
                'reason' => 'Used the highest similarity FAQ and approved response guidance.',
            ],
        ])->preventStrayPrompts();

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Können Aktien als Vermögenswert umgewandelt werden?',
                'normalized_question' => 'Können Aktien als Vermögenswert nach dem SAG umgewandelt werden?',
            ]);

        $faqEntry = FaqEntry::factory()->create([
            'question' => 'Welche Vermögenswerte können betroffen sein?',
            'answer' => 'Verschiedene Vermögenswerte können von regulatorischen Vorgaben betroffen sein.',
        ]);
        FaqApprovedResponse::query()->create([
            'faq_entry_id' => $faqEntry->id,
            'approved_response' => 'Bitte verweisen Sie auf ein persönliches Gespräch.',
        ]);
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
            'faq_entry_id' => $faqEntry->id,
            'rank' => 1,
            'similarity' => 0.91,
            'distance' => 0.09,
        ]);

        $draft = app(EmailQuestionAnswerDraftGenerationService::class)->generate($question);

        $this->assertSame($question->id, $draft->email_question_id);
        $this->assertSame(EmailQuestionAnswerDraft::StatusDraft, $draft->status);
        $this->assertSame('Ja, Aktien können im Rahmen der Umwandlung besprochen werden.', $draft->generated_answer);
        $this->assertSame($draft->generated_answer, $draft->final_answer);
        $this->assertSame('Used the highest similarity FAQ and approved response guidance.', $draft->generation_reason);
        $this->assertSame([$faqEntry->id], $draft->generation_metadata['faq_entry_ids']);
        $this->assertNotNull($draft->generated_at);

        EmailQuestionAnswerGenerator::assertPrompted(
            fn (AgentPrompt $prompt): bool => $prompt->contains('Können Aktien als Vermögenswert nach dem SAG umgewandelt werden?')
                && $prompt->contains('Welche Vermögenswerte können betroffen sein?')
                && $prompt->contains('Bitte verweisen Sie auf ein persönliches Gespräch.'),
        );
    }

    public function test_command_queues_only_ready_answer_drafts(): void
    {
        Queue::fake();

        $readyQuestion = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $readyQuestion->id,
        ]);

        EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create();

        $noiseQuestion = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusNoise)
            ->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $noiseQuestion->id,
        ]);

        $this->artisan('email-questions:generate-answer-drafts')
            ->expectsOutput('Queued answer draft generation for 1 email question(s).')
            ->assertSuccessful();

        $this->assertSame(1, EmailQuestionAnswerDraft::query()->count());
        $draft = EmailQuestionAnswerDraft::query()->sole();

        $this->assertSame($readyQuestion->id, $draft->email_question_id);
        $this->assertSame(EmailQuestionAnswerDraft::StatusQueued, $draft->status);
        $this->assertNull($draft->generated_at);
        $this->assertNull($draft->generation_started_at);

        Queue::assertPushed(
            GenerateEmailQuestionAnswerDraft::class,
            fn (GenerateEmailQuestionAnswerDraft $job): bool => $job->emailQuestionId === $readyQuestion->id,
        );
    }

    public function test_answer_draft_job_generates_draft_and_updates_status(): void
    {
        EmailQuestionAnswerGenerator::fake([
            [
                'answer' => 'Das ist ein geprüfter Antwortentwurf.',
                'reason' => 'Ready question with retrieved FAQ context.',
            ],
        ])->preventStrayPrompts();

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create();
        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $question->id,
        ]);

        (new GenerateEmailQuestionAnswerDraft($question->id))
            ->handle(app(EmailQuestionAnswerDraftGenerationService::class));

        $draft = EmailQuestionAnswerDraft::query()->whereBelongsTo($question)->sole();

        $this->assertSame(EmailQuestionAnswerDraft::StatusDraft, $draft->status);
        $this->assertSame('Das ist ein geprüfter Antwortentwurf.', $draft->generated_answer);
        $this->assertNotNull($draft->generation_started_at);
        $this->assertNull($draft->generation_failed_at);
    }
}
