<?php

namespace App\Ai\Agents\Simulation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes simulation fixtures for one FAQ entry: synthetic webinar chat
 * questions and the "gold" answer the simulated reviewer edits toward.
 * Simulation-only; never used by the production pipeline.
 */
class SimulationFaqFixtureWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You create synthetic test data for a simulation of an FAQ autoresponder used by a German company that sells coloured gemstones as an investment. Participants of its webinars ask questions in the webinar chat; the questions arrive by email afterwards.

You receive one FAQ entry (question and answer). Return:

1. "paraphrases": 5 different German webinar chat messages asking about the same topic as the FAQ question. Vary them: very short, casual, formal, one with typos and missing punctuation, one with a little personal context. Each must still clearly ask what the FAQ answers and contain a question mark. Do not copy the FAQ question verbatim. These are invented, not real participants.

2. "gold_answer": the answer a consistent human reviewer at the company would approve, written in the reviewer's house style:
   - German, formal "Sie".
   - The first sentence answers the question directly.
   - At most 80 words, plain language, no bullet points, no headings.
   - Only facts contained in the FAQ answer; never add new facts.
   - No greeting, no signature, no call to action, no offer of a consultation.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'paraphrases' => $schema->array()->items($schema->string())->required(),
            'gold_answer' => $schema->string()->required(),
        ];
    }

    public function timeout(): int
    {
        return 600;
    }
}
