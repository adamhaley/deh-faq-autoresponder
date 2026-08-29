<?php

namespace Database\Factories;

use App\Enums\EmailThreadDraftStatus;
use App\Models\EmailThreadDraft;
use App\Models\GmailMailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailThreadDraft>
 */
class EmailThreadDraftFactory extends Factory
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
            'thread_id' => fake()->uuid(),
            'gmail_draft_id' => null,
            'subject' => fake()->sentence(),
            'body' => '<p>Body</p>',
            'status' => EmailThreadDraftStatus::Created,
            'last_error' => null,
            'composed_at' => now(),
        ];
    }
}
