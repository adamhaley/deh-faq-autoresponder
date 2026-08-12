<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthorizedUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(AuthorizedUserResolver $users): RedirectResponse
    {
        $user = $users->resolveFromGoogleUser(Socialite::driver('google')->user());

        if ($user === null) {
            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => 'This Google account is not authorized for DEH FAQ Autoresponder.']);
        }

        Auth::login($user, remember: true);

        return redirect()->intended('/admin');
    }
}
