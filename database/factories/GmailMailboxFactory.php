<?php

namespace Database\Factories;

use App\Models\GmailMailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GmailMailbox>
 */
class GmailMailboxFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
            'label_ids' => GmailMailbox::DefaultLabelIds,
            'last_history_id' => (string) fake()->numberBetween(1000, 9999),
            'is_active' => true,
            'sync_status' => GmailMailbox::SyncStatusConnected,
        ];
    }
}
