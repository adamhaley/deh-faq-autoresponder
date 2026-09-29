<?php

namespace App\Services\Faq;

use Laravel\Ai\Embeddings;

/**
 * Generates the pgvector-format embedding for a manually-authored FAQ
 * entry, using the same model and dimensionality as retrieval so admin-
 * created entries match incoming questions exactly like seeded ones do.
 *
 * Embeds the question alone, not question+answer -- retrieval always
 * embeds the incoming customer question, so the corpus side should be the
 * same kind of text for the cosine-distance comparison to be meaningful.
 */
class FaqEntryEmbeddingService
{
    public function embed(string $question): string
    {
        $embeddingModel = (string) config('services.openai.embedding_model', 'text-embedding-3-small');

        $vector = Embeddings::for([$question])
            ->dimensions(1536)
            ->cache()
            ->generate(model: $embeddingModel)
            ->first();

        return json_encode($vector, JSON_THROW_ON_ERROR);
    }
}
