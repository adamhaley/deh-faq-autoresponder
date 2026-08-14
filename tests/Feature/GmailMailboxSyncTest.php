<?php

namespace Tests\Feature;

use App\Models\GmailMailbox;
use App\Models\GmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GmailMailboxSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_imports_new_messages_from_active_mailboxes(): void
    {
        $this->configureGmail();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'fresh-access-token',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/gmail.modify',
                ]);
            }

            if (str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/history')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                $this->assertSame('100', $query['startHistoryId']);
                $this->assertSame('messageAdded', $query['historyTypes']);
                $this->assertSame('INBOX', $query['labelId']);

                return Http::response([
                    'historyId' => '200',
                    'history' => [
                        [
                            'messagesAdded' => [
                                ['message' => ['id' => 'message-1']],
                                ['message' => ['id' => 'message-2']],
                            ],
                        ],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/messages/message-1')) {
                return Http::response($this->gmailMessage([
                    'id' => 'message-1',
                    'threadId' => 'thread-1',
                    'historyId' => '190',
                    'subject' => 'First question',
                    'from' => 'Customer One <one@example.com>',
                    'body' => 'How do I care for this stone?',
                    'internalDate' => '1786530000000',
                ]));
            }

            if (str_contains($request->url(), '/messages/message-2')) {
                return Http::response($this->gmailMessage([
                    'id' => 'message-2',
                    'threadId' => 'thread-2',
                    'historyId' => '191',
                    'subject' => 'Second question',
                    'from' => 'two@example.com',
                    'body' => 'Do you ship internationally?',
                    'internalDate' => '1786530060000',
                ]));
            }

            return Http::response(status: 404);
        });

        $mailbox = GmailMailbox::factory()->create([
            'access_token' => 'stale-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subDay(),
            'last_history_id' => '100',
        ]);

        $this->artisan('gmail:sync-mailboxes')
            ->expectsOutput('Imported 2 Gmail message(s).')
            ->assertSuccessful();

        $mailbox = $mailbox->fresh();

        $this->assertInstanceOf(GmailMailbox::class, $mailbox);

        $this->assertSame('fresh-access-token', $mailbox->access_token);
        $this->assertSame('200', $mailbox->last_history_id);
        $this->assertSame(GmailMailbox::SyncStatusConnected, $mailbox->sync_status);
        $this->assertNull($mailbox->last_error);
        $this->assertNotNull($mailbox->last_sync_at);

        $this->assertSame(2, GmailMessage::query()->count());

        $message = GmailMessage::query()->where('gmail_message_id', 'message-1')->firstOrFail();

        $this->assertSame($mailbox->id, $message->gmail_mailbox_id);
        $this->assertSame('First question', $message->subject);
        $this->assertSame('one@example.com', $message->from_email);
        $this->assertSame('Customer One', $message->from_name);
        $this->assertSame('How do I care for this stone?', $message->text_body);
        $this->assertSame(['customer@example.com'], $message->to_recipients);
        $this->assertSame(['INBOX'], $message->label_ids);
    }

    public function test_command_extracts_participant_name_and_reply_email_from_message_body(): void
    {
        $this->configureGmail();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'fresh-access-token',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/gmail.modify',
                ]);
            }

            if (str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/history')) {
                return Http::response([
                    'historyId' => '200',
                    'history' => [
                        ['messagesAdded' => [['message' => ['id' => 'message-1']]]],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/messages/message-1')) {
                return Http::response($this->gmailMessage([
                    'id' => 'message-1',
                    'threadId' => 'thread-1',
                    'historyId' => '190',
                    'subject' => 'Webinar question',
                    'from' => 'webinar-platform@example.com',
                    'body' => "Vorname: Maria\nNachname: Schmidt\nE-Mail: maria.schmidt@example.com\n\nChat:\n1: Maria (10:00): Wie funktioniert das Investment?",
                    'internalDate' => '1786530000000',
                ]));
            }

            return Http::response(status: 404);
        });

        $mailbox = GmailMailbox::factory()->create(['last_history_id' => '100']);

        $this->artisan('gmail:sync-mailboxes')->assertSuccessful();

        $message = GmailMessage::query()->where('gmail_message_id', 'message-1')->firstOrFail();

        $this->assertSame('Maria Schmidt', $message->participant_name);
        $this->assertSame('maria.schmidt@example.com', $message->reply_to_email);
        $this->assertSame('webinar-platform@example.com', $message->from_email);
    }

    public function test_command_backfills_and_recovers_a_mailbox_needing_resync(): void
    {
        $this->configureGmail();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                return Http::response([
                    'access_token' => 'fresh-access-token',
                    'expires_in' => 3600,
                    'scope' => 'https://www.googleapis.com/auth/gmail.modify',
                ]);
            }

            if (str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/messages?')) {
                parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

                $this->assertSame('INBOX', $query['labelIds']);
                $this->assertStringStartsWith('after:', $query['q']);

                return Http::response([
                    'messages' => [
                        ['id' => 'message-1'],
                        ['id' => 'message-2'],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/messages/message-1')) {
                return Http::response($this->gmailMessage([
                    'id' => 'message-1',
                    'threadId' => 'thread-1',
                    'historyId' => '190',
                    'subject' => 'First question',
                    'from' => 'Customer One <one@example.com>',
                    'body' => 'How do I care for this stone?',
                    'internalDate' => '1786530000000',
                ]));
            }

            if (str_contains($request->url(), '/messages/message-2')) {
                return Http::response($this->gmailMessage([
                    'id' => 'message-2',
                    'threadId' => 'thread-2',
                    'historyId' => '191',
                    'subject' => 'Second question',
                    'from' => 'two@example.com',
                    'body' => 'Do you ship internationally?',
                    'internalDate' => '1786530060000',
                ]));
            }

            if (str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/profile')) {
                return Http::response(['historyId' => '400']);
            }

            return Http::response(status: 404);
        });

        $mailbox = GmailMailbox::factory()->create([
            'access_token' => 'stale-access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => now()->subDay(),
            'last_history_id' => '100',
            'sync_status' => GmailMailbox::SyncStatusResyncRequired,
            'last_error' => 'HTTP request returned status code 404',
        ]);

        $this->artisan('gmail:sync-mailboxes')
            ->expectsOutput('Imported 2 Gmail message(s).')
            ->assertSuccessful();

        $mailbox = $mailbox->fresh();

        $this->assertInstanceOf(GmailMailbox::class, $mailbox);
        $this->assertSame('400', $mailbox->last_history_id);
        $this->assertSame(GmailMailbox::SyncStatusConnected, $mailbox->sync_status);
        $this->assertNull($mailbox->last_error);
        $this->assertSame(2, GmailMessage::query()->count());
    }

    public function test_command_skips_inactive_mailboxes(): void
    {
        $this->configureGmail();

        Http::preventStrayRequests();
        Http::fake();

        GmailMailbox::factory()->create(['is_active' => false]);

        $this->artisan('gmail:sync-mailboxes')
            ->expectsOutput('Imported 0 Gmail message(s).')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_one_mailbox_failure_does_not_block_other_mailboxes(): void
    {
        $this->configureGmail();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $authorization = $request->header('Authorization')[0] ?? '';

            if (
                str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/history')
                && $authorization === 'Bearer bad-access-token'
            ) {
                return Http::response(['error' => ['message' => 'Temporary failure']], 500);
            }

            if (
                str_starts_with($request->url(), 'https://gmail.googleapis.com/gmail/v1/users/me/history')
                && $authorization === 'Bearer good-access-token'
            ) {
                return Http::response(['historyId' => '300']);
            }

            return Http::response(status: 404);
        });

        $failedMailbox = GmailMailbox::factory()->create([
            'access_token' => 'bad-access-token',
            'token_expires_at' => now()->addHour(),
            'last_history_id' => '100',
        ]);

        $successfulMailbox = GmailMailbox::factory()->create([
            'access_token' => 'good-access-token',
            'token_expires_at' => now()->addHour(),
            'last_history_id' => '200',
        ]);

        $this->artisan('gmail:sync-mailboxes')
            ->expectsOutput('Imported 0 Gmail message(s).')
            ->assertSuccessful();

        $this->assertSame(GmailMailbox::SyncStatusFailed, $failedMailbox->fresh()->sync_status);
        $this->assertSame(GmailMailbox::SyncStatusConnected, $successfulMailbox->fresh()->sync_status);
        $this->assertSame('300', $successfulMailbox->fresh()->last_history_id);
    }

    private function configureGmail(): void
    {
        config()->set('services.gmail.client_id', 'gmail-client-id');
        config()->set('services.gmail.client_secret', 'gmail-client-secret');
    }

    /**
     * @param  array{id: string, threadId: string, historyId: string, subject: string, from: string, body: string, internalDate: string}  $attributes
     * @return array<string, mixed>
     */
    private function gmailMessage(array $attributes): array
    {
        return [
            'id' => $attributes['id'],
            'threadId' => $attributes['threadId'],
            'historyId' => $attributes['historyId'],
            'labelIds' => ['INBOX'],
            'snippet' => $attributes['body'],
            'internalDate' => $attributes['internalDate'],
            'payload' => [
                'mimeType' => 'multipart/alternative',
                'headers' => [
                    ['name' => 'Subject', 'value' => $attributes['subject']],
                    ['name' => 'From', 'value' => $attributes['from']],
                    ['name' => 'To', 'value' => 'customer@example.com'],
                ],
                'parts' => [
                    [
                        'mimeType' => 'text/plain',
                        'body' => [
                            'data' => $this->base64Url($attributes['body']),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
