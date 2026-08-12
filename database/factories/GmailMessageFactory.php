<?php

namespace Database\Factories;

use App\Models\GmailMailbox;
use App\Models\GmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GmailMessage>
 */
class GmailMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gmail_mailbox_id' => GmailMailbox::factory(),
            'gmail_message_id' => fake()->unique()->bothify('msg-####'),
            'thread_id' => fake()->bothify('thread-####'),
            'history_id' => (string) fake()->numberBetween(1000, 9999),
            'subject' => fake()->sentence(),
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'to_recipients' => [fake()->safeEmail()],
            'cc_recipients' => [],
            'snippet' => fake()->sentence(),
            'text_body' => fake()->paragraph(),
            'html_body' => null,
            'label_ids' => ['INBOX'],
            'internal_date' => now(),
            'payload' => ['id' => fake()->uuid()],
            'imported_at' => now(),
        ];
    }
}
