<?php

namespace Database\Factories;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailQuestionAnswerDraft>
 */
class EmailQuestionAnswerDraftFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_question_id' => EmailQuestion::factory(),
            'generated_answer' => fake()->paragraph(),
            'final_answer' => null,
            'status' => EmailQuestionAnswerDraft::StatusDraft,
            'generation_reason' => 'Factory generated draft.',
            'generation_metadata' => ['source' => 'factory'],
            'generated_at' => now(),
            'reviewed_at' => null,
        ];
    }
}
