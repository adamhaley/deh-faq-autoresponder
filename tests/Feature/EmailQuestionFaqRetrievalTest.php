<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailQuestions\EmailQuestionResource;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqApprovedResponse;
use App\Models\FaqEntry;
use App\Services\EmailQuestions\EmailQuestionFaqRetrievalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_command_retrieves_matches_for_reviewed_valid_questions(): void
    {
        Embeddings::fake([[$this->embedding([1.0, 0.0])]]);

        FaqEntry::factory()->create([
            'question' => 'Wie funktioniert ein Edelstein-Investment?',
            'answer' => 'Sie erwerben physische Edelsteine.',
            'embedding' => $this->vector([1.0, 0.0]),
        ]);

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
            ->expectsOutput('Retrieved FAQ matches for 1 email question(s).')
            ->assertSuccessful();

        $this->assertSame(1, $validQuestion->faqMatches()->count());
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
