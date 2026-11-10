<?php

namespace App\Ai\Agents\Simulation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Writes the simulation's out-of-scope questions and chat noise.
 * Simulation-only; never used by the production pipeline.
 */
class SimulationOffTopicFixtureWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You create synthetic test data for a simulation of an FAQ autoresponder used by a German company that sells coloured gemstones as an investment. Participants write in the webinar chat; their messages arrive by email afterwards. All messages are invented and in German, and every message contains a question mark.

Return:

1. "out_of_scope": 12 real questions that the company's gemstone-investment FAQ cannot answer, e.g. personal tax situations, unrelated products, other companies, questions about the participant's own unrelated finances.

2. "noise": 12 chat messages that need no answer at all, e.g. "Kann man mich hören?", "Ist die Aufzeichnung später verfügbar?" style technical chatter, greetings with a question mark, thanks, small talk.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'out_of_scope' => $schema->array()->items($schema->string())->required(),
            'noise' => $schema->array()->items($schema->string())->required(),
        ];
    }

    public function timeout(): int
    {
        return 600;
    }
}
