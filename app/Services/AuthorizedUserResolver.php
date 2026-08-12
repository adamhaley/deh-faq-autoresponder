<?php

namespace App\Services;

use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class AuthorizedUserResolver
{
    public function resolveFromGoogleUser(SocialiteUser $googleUser): ?User
    {
        $email = $this->normalizeEmail($googleUser->getEmail());

        if ($email === null) {
            return null;
        }

        $authorizedEmail = AuthorizedEmail::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if ($authorizedEmail === null) {
            return null;
        }

        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $googleUser->getName() ?: $email,
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'role' => $authorizedEmail->role,
                'is_active' => true,
                'email_verified_at' => now(),
                'last_login_at' => now(),
            ],
        );
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! filled($email)) {
            return null;
        }

        return Str::lower(trim($email));
    }
}
