<?php

namespace App\Services\Gmail;

use App\Models\GmailMailbox;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailClient
{
    private const ApiBaseUrl = 'https://gmail.googleapis.com/gmail/v1';

    private const TokenEndpoint = 'https://oauth2.googleapis.com/token';

    public function accessTokenFor(GmailMailbox $mailbox): string
    {
        if (
            is_string($mailbox->access_token)
            && $mailbox->access_token !== ''
            && $mailbox->token_expires_at !== null
            && $mailbox->token_expires_at->timestamp > now()->addMinute()->timestamp
        ) {
            return $mailbox->access_token;
        }

        return $this->refreshAccessToken($mailbox);
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(GmailMailbox $mailbox): array
    {
        return $this->send($mailbox, fn (PendingRequest $request): Response => $request->get('users/me/profile'))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function history(GmailMailbox $mailbox, ?string $pageToken = null): array
    {
        $labelIds = $mailbox->syncLabelIds();

        return $this->send($mailbox, fn (PendingRequest $request): Response => $request->get('users/me/history', array_filter([
            'startHistoryId' => $mailbox->last_history_id,
            'historyTypes' => 'messageAdded',
            'labelId' => $labelIds[0] ?? null,
            'pageToken' => $pageToken,
            'maxResults' => 100,
        ], fn (mixed $value): bool => $value !== null)))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function listMessages(GmailMailbox $mailbox, ?string $pageToken = null, ?int $afterUnixTimestamp = null): array
    {
        $labelIds = $mailbox->syncLabelIds();

        return $this->send($mailbox, fn (PendingRequest $request): Response => $request->get('users/me/messages', array_filter([
            'labelIds' => $labelIds[0] ?? null,
            'q' => $afterUnixTimestamp !== null ? "after:{$afterUnixTimestamp}" : null,
            'pageToken' => $pageToken,
            'maxResults' => 100,
        ], fn (mixed $value): bool => $value !== null)))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function message(GmailMailbox $mailbox, string $messageId): array
    {
        return $this->send($mailbox, fn (PendingRequest $request): Response => $request->get("users/me/messages/{$messageId}", ['format' => 'full']))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function createDraft(GmailMailbox $mailbox, string $rawMessage, ?string $threadId = null): array
    {
        return $this->send($mailbox, fn (PendingRequest $request): Response => $request->post('users/me/drafts', [
            'message' => array_filter([
                'raw' => $rawMessage,
                'threadId' => $threadId,
            ], fn (mixed $value): bool => $value !== null),
        ]))->json();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateDraft(GmailMailbox $mailbox, string $draftId, string $rawMessage, ?string $threadId = null): array
    {
        return $this->send($mailbox, fn (PendingRequest $request): Response => $request->put("users/me/drafts/{$draftId}", [
            'message' => array_filter([
                'raw' => $rawMessage,
                'threadId' => $threadId,
            ], fn (mixed $value): bool => $value !== null),
        ]))->json();
    }

    /**
     * Executes a request, and if Gmail rejects the token with a 401 despite
     * our locally-computed expiry saying it should still be valid (clock
     * skew, an early revocation, or any other drift between our bookkeeping
     * and Google's actual state), forces a fresh refresh and retries once
     * before giving up. Without this, a single stale-but-not-yet-"expired"
     * token would keep failing every call until something else noticed.
     */
    private function send(GmailMailbox $mailbox, Closure $makeRequest): Response
    {
        $response = $makeRequest($this->request($mailbox));

        if ($response->status() === 401) {
            $this->refreshAccessToken($mailbox);
            $response = $makeRequest($this->request($mailbox));
        }

        return $response->throw();
    }

    private function refreshAccessToken(GmailMailbox $mailbox): string
    {
        if (! is_string($mailbox->refresh_token) || $mailbox->refresh_token === '') {
            throw new RuntimeException("Gmail mailbox {$mailbox->email} does not have a refresh token.");
        }

        $tokenData = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(3)
            ->retry(2, 250)
            ->post(self::TokenEndpoint, [
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'refresh_token' => $mailbox->refresh_token,
                'grant_type' => 'refresh_token',
            ])
            ->throw()
            ->json();

        $accessToken = $tokenData['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Google did not return an access token while refreshing Gmail credentials.');
        }

        $mailbox->update([
            'access_token' => $accessToken,
            'token_expires_at' => now()->addSeconds((int) ($tokenData['expires_in'] ?? 0)),
            'scopes' => $this->responseScopes($tokenData, $mailbox),
        ]);

        return $accessToken;
    }

    private function request(GmailMailbox $mailbox): PendingRequest
    {
        return Http::baseUrl(self::ApiBaseUrl)
            ->withToken($this->accessTokenFor($mailbox))
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(3)
            // Scoped to connection failures only (throw: false, and `when`
            // excludes HTTP error responses) -- otherwise retry()'s default
            // behavior retries and eventually throws on a 401 by itself,
            // before send()'s own refresh-and-retry logic ever runs.
            ->retry(2, 250, fn (\Throwable $exception): bool => $exception instanceof ConnectionException, throw: false);
    }

    private function config(string $key): string
    {
        $value = config("services.gmail.{$key}");

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Missing Gmail OAuth configuration value: services.gmail.{$key}");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $tokenData
     * @return list<string>
     */
    private function responseScopes(array $tokenData, GmailMailbox $mailbox): array
    {
        if (! isset($tokenData['scope']) || ! is_string($tokenData['scope'])) {
            return is_array($mailbox->scopes) ? array_values($mailbox->scopes) : [GmailOAuthService::ScopeModify];
        }

        return array_values(array_filter(explode(' ', $tokenData['scope'])));
    }
}
