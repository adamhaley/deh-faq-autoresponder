<?php

namespace App\Services\Gmail;

use App\Enums\GmailMailboxSyncStatus;
use App\Models\GmailMailbox;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GmailOAuthService
{
    public const ScopeModify = 'https://www.googleapis.com/auth/gmail.modify';

    private const AuthorizationEndpoint = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const ProfileEndpoint = 'https://gmail.googleapis.com/gmail/v1/users/me/profile';

    private const TokenEndpoint = 'https://oauth2.googleapis.com/token';

    public function authorizationUrl(string $state): string
    {
        return self::AuthorizationEndpoint.'?'.Arr::query([
            'client_id' => $this->config('client_id'),
            'redirect_uri' => $this->config('redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes()),
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function connectMailbox(string $code, User $connectedBy): GmailMailbox
    {
        $tokenData = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(3)
            ->retry(2, 250)
            ->post(self::TokenEndpoint, [
                'client_id' => $this->config('client_id'),
                'client_secret' => $this->config('client_secret'),
                'redirect_uri' => $this->config('redirect'),
                'grant_type' => 'authorization_code',
                'code' => $code,
            ])
            ->throw()
            ->json();

        $accessToken = $this->stringFromResponse($tokenData, 'access_token');

        $profileData = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout(10)
            ->connectTimeout(3)
            ->retry(2, 250)
            ->get(self::ProfileEndpoint)
            ->throw()
            ->json();

        $email = strtolower($this->stringFromResponse($profileData, 'emailAddress'));
        $existingMailbox = GmailMailbox::query()->where('email', $email)->first();
        $refreshToken = $tokenData['refresh_token'] ?? $existingMailbox?->refresh_token;

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException('Google did not return a refresh token for this mailbox.');
        }

        return GmailMailbox::query()->updateOrCreate(
            ['email' => $email],
            [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => now()->addSeconds((int) ($tokenData['expires_in'] ?? 0)),
                'scopes' => $this->responseScopes($tokenData),
                'last_history_id' => $profileData['historyId'] ?? null,
                'is_active' => true,
                'sync_status' => GmailMailboxSyncStatus::Connected,
                'last_error' => null,
                'connected_by_user_id' => $connectedBy->id,
            ],
        );
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return [self::ScopeModify];
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
     * @param  array<string, mixed>  $response
     */
    private function stringFromResponse(array $response, string $key): string
    {
        $value = $response[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Google OAuth response did not include {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $tokenData
     * @return list<string>
     */
    private function responseScopes(array $tokenData): array
    {
        if (! isset($tokenData['scope']) || ! is_string($tokenData['scope'])) {
            return $this->scopes();
        }

        return array_values(array_filter(explode(' ', $tokenData['scope'])));
    }
}
