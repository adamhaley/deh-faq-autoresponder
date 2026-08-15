<?php

namespace Tests\Feature;

use App\Models\GmailMailbox;
use App\Services\Gmail\GmailClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_and_retries_once_when_gmail_rejects_a_token_it_believes_is_still_valid(): void
    {
        config()->set('services.gmail.client_id', 'gmail-client-id');
        config()->set('services.gmail.client_secret', 'gmail-client-secret');

        // token_expires_at says this token is still valid for another hour,
        // but Gmail itself will reject it -- this is exactly the drift
        // scenario the reactive refresh-and-retry exists to handle.
        $mailbox = GmailMailbox::factory()->create([
            'access_token' => 'stale-but-not-yet-expired-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $attempt = 0;

        Http::preventStrayRequests();
        Http::fake(function (Request $request) use (&$attempt, $mailbox) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'fresh-token',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/gmail.modify',
                ]);
            }

            if (str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/profile')) {
                $attempt++;

                if ($request->header('Authorization')[0] === 'Bearer stale-but-not-yet-expired-token') {
                    return Http::response(['error' => ['code' => 401, 'message' => 'Invalid Credentials']], 401);
                }

                return Http::response(['emailAddress' => $mailbox->email, 'historyId' => '123']);
            }

            return Http::response(status: 404);
        });

        $profile = app(GmailClient::class)->profile($mailbox);

        $this->assertSame(2, $attempt);
        $this->assertSame($mailbox->email, $profile['emailAddress']);
        $this->assertSame('fresh-token', $mailbox->fresh()->access_token);
    }
}
