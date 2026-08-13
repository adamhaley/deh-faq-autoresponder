<?php

namespace Database\Factories;

use App\Models\EmailQuestion;
use App\Models\EmailQuestionFaqMatch;
use App\Models\FaqEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailQuestionFaqMatch>
 */
class EmailQuestionFaqMatchFactory extends Factory
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
            'faq_entry_id' => FaqEntry::factory(),
            'rank' => fake()->numberBetween(1, 5),
            'similarity' => fake()->randomFloat(4, 0.6, 1.0),
            'distance' => fake()->randomFloat(4, 0.0, 0.4),
            'retrieval_metadata' => ['source' => 'factory'],
            'retrieved_at' => now(),
        ];
    }
}
