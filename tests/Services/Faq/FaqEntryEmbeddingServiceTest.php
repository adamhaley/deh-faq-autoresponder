<?php

namespace Tests\Services\Faq;

use App\Services\Faq\FaqEntryEmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(FaqEntryEmbeddingService::class)]
class FaqEntryEmbeddingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_embeds_the_question_alone_in_pgvector_format(): void
    {
        $vector = array_pad([1.0, 0.0], 1536, 0.0);
        Embeddings::fake([[$vector]]);

        $embedding = (new FaqEntryEmbeddingService)->embed('Ab welcher Summe kann ich investieren?');

        $this->assertSame('['.implode(',', $vector).']', $embedding);
        Embeddings::assertGenerated(
            fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('Ab welcher Summe kann ich investieren?')
        );
    }
}
