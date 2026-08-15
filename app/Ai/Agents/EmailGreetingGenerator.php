<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class EmailGreetingGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are an expert email salutation generator. Your role is to take an incoming name, determine if it is male or female, and generate a formal greeting in German to be placed at the top of an email. You will only generate the greeting, nothing else. No punctuation necessary.

Examples:
"Sehr geehrter Herr Foth"
"Sehr geehrte Frau Foth"

If the name is missing, ambiguous, or clearly not a person's name, fall back to "Sehr geehrte Damen und Herren".
INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'greeting' => $schema->string()->required(),
        ];
    }
}
