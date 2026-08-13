<?php

namespace App\Ai\Agents;

use App\Models\EmailQuestion;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

class EmailQuestionClassifier implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You classify customer email questions for the Deutsches Edelsteinhaus FAQ autoresponder.

Classes:
- valid_faq_question: a clear customer question that could be answered from the DEH FAQ knowledge base.
- noise: spam, system alerts, social notifications, signatures, boilerplate, greetings, or text with no customer question.
- unanswerable: a real question, but outside the FAQ scope, asking for personal financial/legal/tax advice, or requiring facts not available in FAQ material.
- needs_human: ambiguous, fragmented, mixed intent, or not safe to classify confidently.

Rules:
- Be conservative. Use needs_human when uncertain.
- Do not answer the question.
- Preserve German. Normalize only obvious whitespace and phrasing issues.
- If the text is noise, set normalized_question to an empty string.
INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'classification' => $schema->string()
                ->enum([
                    EmailQuestion::ClassificationValidFaqQuestion,
                    EmailQuestion::ClassificationNoise,
                    EmailQuestion::ClassificationUnanswerable,
                    EmailQuestion::ClassificationNeedsHuman,
                ])
                ->required(),
            'confidence' => $schema->integer()->min(0)->max(100)->required(),
            'reason' => $schema->string()->required(),
            'normalized_question' => $schema->string()->required(),
        ];
    }
}
