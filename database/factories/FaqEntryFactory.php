<?php

namespace Database\Factories;

use App\Models\FaqEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FaqEntry>
 */
class FaqEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question' => fake()->sentence().'?',
            'answer' => fake()->paragraph(),
            'embedding' => $this->embedding(),
        ];
    }

    private function embedding(): string
    {
        $values = array_fill(0, 1536, '0');
        $values[0] = '1';

        return '['.implode(',', $values).']';
    }
}
