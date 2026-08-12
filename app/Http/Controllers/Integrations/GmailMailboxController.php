<?php

namespace App\Http\Controllers\Integrations;

use App\Enums\UserRole;
use App\Filament\Resources\GmailMailboxes\GmailMailboxResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Gmail\GmailOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GmailMailboxController extends Controller
{
    private const StateSessionKey = 'gmail_oauth_state';

    public function redirect(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $state = Str::random(40);
        $request->session()->put(self::StateSessionKey, $state);

        try {
            return redirect()->away($gmail->authorizationUrl($state));
        } catch (Throwable $exception) {
            $request->session()->forget(self::StateSessionKey);

            report($exception);

            return redirect(GmailMailboxResource::getUrl())
                ->withErrors(['gmail' => 'Gmail OAuth is not configured. Set GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET, and GMAIL_REDIRECT_URI.']);
        }
    }

    public function callback(Request $request, GmailOAuthService $gmail): RedirectResponse
    {
        $user = $this->authorizeAdmin($request);
        $expectedState = $request->session()->pull(self::StateSessionKey);
        $receivedState = $request->query('state');

        abort_unless(
            is_string($expectedState) && is_string($receivedState) && hash_equals($expectedState, $receivedState),
            403,
        );

        if ($request->filled('error')) {
            return redirect(GmailMailboxResource::getUrl())
                ->withErrors(['gmail' => (string) $request->query('error_description', $request->query('error'))]);
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect(GmailMailboxResource::getUrl())
                ->withErrors(['gmail' => 'Google did not return an authorization code.']);
        }

        try {
            $mailbox = $gmail->connectMailbox($code, $user);
        } catch (Throwable $exception) {
            report($exception);

            return redirect(GmailMailboxResource::getUrl())
                ->withErrors(['gmail' => 'Gmail mailbox connection failed. Check the logs for details.']);
        }

        return redirect(GmailMailboxResource::getUrl())
            ->with('status', "Connected Gmail mailbox {$mailbox->email}.");
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->role === UserRole::Admin, 403);

        return $user;
    }
}
