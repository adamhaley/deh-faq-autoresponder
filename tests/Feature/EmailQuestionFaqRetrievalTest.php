<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailQuestions\EmailQuestionResource;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Jobs\RetrieveEmailQuestionFaqMatches;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqApprovedResponse;
use App\Models\FaqEntry;
use App\Services\EmailQuestions\EmailQuestionFaqRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;

class EmailQuestionFaqRetrievalTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_retrieves_and_persists_ranked_faq_matches(): void
    {
        $queryEmbedding = $this->embedding([1.0, 0.0]);
        Embeddings::fake([[$queryEmbedding]]);

        $bestMatch = FaqEntry::factory()->create([
            'question' => 'Wie funktioniert ein Edelstein-Investment?',
            'answer' => 'Sie erwerben physische Edelsteine.',
            'embedding' => $this->vector([1.0, 0.0]),
        ]);
        FaqApprovedResponse::query()->create([
            'faq_entry_id' => $bestMatch->id,
            'approved_response' => 'Approved response text.',
            'match_similarity' => 0.98,
        ]);

        $secondMatch = FaqEntry::factory()->create([
            'question' => 'Wie lagere ich Edelsteine?',
            'answer' => 'Die Lagerung erfolgt sicher.',
            'embedding' => $this->vector([0.8, 0.6]),
        ]);

        FaqEntry::factory()->create([
            'question' => 'Was ist ein Zertifikat?',
            'answer' => 'Ein Zertifikat dokumentiert Eigenschaften.',
            'embedding' => $this->vector([0.0, 1.0]),
        ]);

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Kann ich in Edelsteine investieren?',
                'normalized_question' => 'Wie funktioniert ein Edelstein-Investment?',
            ]);

        $matches = app(EmailQuestionFaqRetrievalService::class)->retrieve($question, 2);

        $this->assertCount(2, $matches);
        $this->assertSame([$bestMatch->id, $secondMatch->id], $matches->pluck('faq_entry_id')->all());
        $this->assertSame([1, 2], $matches->pluck('rank')->all());
        $this->assertEqualsWithDelta(1.0, $matches->first()->similarity, 0.0001);
        $this->assertSame('Approved response text.', $matches->first()->faqEntry->approvedResponse->approved_response);

        Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('Wie funktioniert ein Edelstein-Investment?')
            && $prompt->dimensions === 1536
            && $prompt->model === 'text-embedding-3-small');
    }

    public function test_marking_a_question_valid_automatically_queues_faq_retrieval(): void
    {
        Queue::fake();

        $question = EmailQuestion::factory()->create([
            'question_text' => 'Kann ich in Edelsteine investieren?',
        ]);

        $question->markReviewed(EmailQuestion::ReviewStatusValid, null);

        $this->assertSame(EmailQuestion::FaqRetrievalStatusQueued, $question->refresh()->faq_retrieval_status);

        Queue::assertPushed(
            RetrieveEmailQuestionFaqMatches::class,
            fn (RetrieveEmailQuestionFaqMatches $job): bool => $job->emailQuestionId === $question->id,
        );
    }

    public function test_marking_a_question_noise_does_not_queue_faq_retrieval(): void
    {
        Queue::fake();

        $question = EmailQuestion::factory()->create([
            'question_text' => 'Hallo',
        ]);

        $question->markReviewed(EmailQuestion::ReviewStatusNoise, null);

        Queue::assertNotPushed(RetrieveEmailQuestionFaqMatches::class);
    }

    public function test_command_queues_match_retrieval_for_reviewed_valid_questions(): void
    {
        Queue::fake();

        $validQuestion = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Kann ich in Edelsteine investieren?',
            ]);

        EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusNoise)
            ->create([
                'question_text' => 'Hallo',
            ]);

        $this->artisan('email-questions:retrieve-faq-matches')
            ->expectsOutput('Queued FAQ match retrieval for 1 email question(s).')
            ->assertSuccessful();

        $this->assertSame(
            EmailQuestion::FaqRetrievalStatusQueued,
            $validQuestion->refresh()->faq_retrieval_status,
        );

        Queue::assertPushed(
            RetrieveEmailQuestionFaqMatches::class,
            fn (RetrieveEmailQuestionFaqMatches $job): bool => $job->emailQuestionId === $validQuestion->id,
        );
    }

    public function test_retrieval_job_retrieves_matches_and_updates_status(): void
    {
        Queue::fake();
        Embeddings::fake([[$this->embedding([1.0, 0.0])]]);

        FaqEntry::factory()->create([
            'question' => 'Wie funktioniert ein Edelstein-Investment?',
            'answer' => 'Sie erwerben physische Edelsteine.',
            'embedding' => $this->vector([1.0, 0.0]),
        ]);

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Kann ich in Edelsteine investieren?',
            ]);

        (new RetrieveEmailQuestionFaqMatches($question->id))
            ->handle(app(EmailQuestionFaqRetrievalService::class));

        $this->assertSame(1, $question->faqMatches()->count());
        $this->assertSame(EmailQuestion::FaqRetrievalStatusCompleted, $question->refresh()->faq_retrieval_status);
        $this->assertNotNull($question->faq_retrieval_started_at);
        $this->assertNotNull($question->faq_retrieval_completed_at);
    }

    public function test_retrieval_job_chains_into_answer_draft_generation(): void
    {
        Queue::fake();
        Embeddings::fake([[$this->embedding([1.0, 0.0])]]);

        FaqEntry::factory()->create([
            'question' => 'Wie funktioniert ein Edelstein-Investment?',
            'answer' => 'Sie erwerben physische Edelsteine.',
            'embedding' => $this->vector([1.0, 0.0]),
        ]);

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Kann ich in Edelsteine investieren?',
            ]);

        (new RetrieveEmailQuestionFaqMatches($question->id))
            ->handle(app(EmailQuestionFaqRetrievalService::class));

        Queue::assertPushed(
            GenerateEmailQuestionAnswerDraft::class,
            fn (GenerateEmailQuestionAnswerDraft $job): bool => $job->emailQuestionId === $question->id,
        );
    }

    public function test_retrieval_job_does_not_chain_when_no_matches_were_found(): void
    {
        Queue::fake();
        Embeddings::fake([[$this->embedding([1.0, 0.0])]]);

        $question = EmailQuestion::factory()
            ->reviewedAs(EmailQuestion::ReviewStatusValid)
            ->create([
                'question_text' => 'Kann ich in Edelsteine investieren?',
            ]);

        (new RetrieveEmailQuestionFaqMatches($question->id))
            ->handle(app(EmailQuestionFaqRetrievalService::class));

        Queue::assertNotPushed(GenerateEmailQuestionAnswerDraft::class);
    }

    public function test_email_question_resource_query_exposes_faq_match_count(): void
    {
        $questionWithMatches = EmailQuestion::factory()->create([
            'question_text' => 'Kann ich in Edelsteine investieren?',
        ]);
        $questionWithoutMatches = EmailQuestion::factory()->create([
            'question_text' => 'Hallo',
        ]);

        EmailQuestionFaqMatch::factory()->create([
            'email_question_id' => $questionWithMatches->id,
            'rank' => 1,
        ]);

        $records = EmailQuestionResource::getEloquentQuery()
            ->whereKey([$questionWithMatches->id, $questionWithoutMatches->id])
            ->get()
            ->keyBy('id');

        $this->assertSame(1, $records[$questionWithMatches->id]->faq_matches_count);
        $this->assertSame(0, $records[$questionWithoutMatches->id]->faq_matches_count);
    }

    /**
     * @param  list<float>  $prefix
     * @return list<float>
     */
    private function embedding(array $prefix): array
    {
        return array_pad($prefix, 1536, 0.0);
    }

    /**
     * @param  list<float>  $prefix
     */
    private function vector(array $prefix): string
    {
        return '['.implode(',', $this->embedding($prefix)).']';
    }
}
