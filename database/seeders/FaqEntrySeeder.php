<?php

namespace Database\Seeders;

use App\Models\FaqEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the canonical FAQ knowledge base from a one-time export of the
 * legacy Supabase `public.faqs` table, embeddings included. This is the
 * only supported way to populate faq_entries in a new environment --
 * growing it further is a deliberate, out-of-band admin task afterward
 * (see docs/implementation-plan.md "Self-Learning Strategy").
 *
 * Idempotent: safe to re-run, keyed on the original faq id.
 */
class FaqEntrySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $path = database_path('seeders/data/faq_entries.json');

        /** @var list<array{id: string, question: string, answer: string, embedding: string}> $entries */
        $entries = json_decode(File::get($path), associative: true, flags: JSON_THROW_ON_ERROR);

        foreach ($entries as $entry) {
            FaqEntry::query()->updateOrCreate(
                ['id' => $entry['id']],
                [
                    'question' => $entry['question'],
                    'answer' => $entry['answer'],
                    'embedding' => $entry['embedding'],
                ],
            );
        }

        $this->command?->info(sprintf('Seeded %d FAQ entries.', count($entries)));
    }
}
