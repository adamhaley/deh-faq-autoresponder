<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\GmailMailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailMailboxOAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_start_gmail_oauth_flow(): void
    {
        config()->set('services.gmail.client_id', 'gmail-client-id');
        config()->set('services.gmail.client_secret', 'gmail-client-secret');
        config()->set('services.gmail.redirect', 'http://localhost:8000/integrations/gmail/callback');

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get(route('integrations.gmail.redirect'));

        $response->assertRedirectContains('https://accounts.google.com/o/oauth2/v2/auth');

        $location = $response->headers->get('Location');
        $this->assertIsString($location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('gmail-client-id', $query['client_id']);
        $this->assertSame('http://localhost:8000/integrations/gmail/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('true', $query['include_granted_scopes']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertStringContainsString('https://www.googleapis.com/auth/gmail.modify', $query['scope']);
        $this->assertIsString($query['state']);
        $this->assertNotSame('', $query['state']);
    }

    public function test_admin_can_complete_gmail_oauth_flow(): void
    {
        config()->set('services.gmail.client_id', 'gmail-client-id');
        config()->set('services.gmail.client_secret', 'gmail-client-secret');
        config()->set('services.gmail.redirect', 'http://localhost:8000/integrations/gmail/callback');

        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'refresh_token' => 'google-refresh-token',
                'expires_in' => 3600,
                'scope' => 'https://www.googleapis.com/auth/gmail.modify',
            ]),
            'https://gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response([
                'emailAddress' => 'Mailbox@Example.com',
                'historyId' => '12345',
            ]),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->withSession(['gmail_oauth_state' => 'known-state'])
            ->get(route('integrations.gmail.callback', [
                'code' => 'google-code',
                'state' => 'known-state',
            ]))
            ->assertRedirect('/admin/gmail-mailboxes');

        $this->assertDatabaseHas(GmailMailbox::class, [
            'email' => 'mailbox@example.com',
            'last_history_id' => '12345',
            'sync_status' => 'connected',
            'is_active' => true,
            'connected_by_user_id' => $admin->id,
        ]);

        $mailbox = GmailMailbox::query()->firstOrFail();

        $this->assertSame('google-access-token', $mailbox->access_token);
        $this->assertSame('google-refresh-token', $mailbox->refresh_token);
        $this->assertSame(['https://www.googleapis.com/auth/gmail.modify'], $mailbox->scopes);
    }

    public function test_non_admin_cannot_start_gmail_oauth_flow(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->get(route('integrations.gmail.redirect'))
            ->assertForbidden();
    }

    public function test_missing_gmail_configuration_redirects_back_with_error(): void
    {
        config()->set('services.gmail.client_id', null);
        config()->set('services.gmail.client_secret', null);
        config()->set('services.gmail.redirect', null);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('integrations.gmail.redirect'))
            ->assertRedirect('/admin/gmail-mailboxes')
            ->assertSessionHasErrors('gmail');

        $this->assertNull(session('gmail_oauth_state'));
    }
}
