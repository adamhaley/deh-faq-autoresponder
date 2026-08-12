<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuthorizedEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_callback_creates_user_for_authorized_email(): void
    {
        AuthorizedEmail::query()->create([
            'email' => 'agent@example.com',
            'role' => UserRole::Reviewer,
        ]);

        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-123',
            'name' => 'DEH Agent',
            'email' => 'Agent@Example.com',
            'avatar' => 'https://example.com/avatar.png',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->assertDatabaseHas(User::class, [
            'email' => 'agent@example.com',
            'google_id' => 'google-123',
            'role' => UserRole::Reviewer->value,
            'is_active' => true,
        ]);
    }

    public function test_google_callback_rejects_unlisted_email(): void
    {
        Socialite::fake('google', SocialiteUser::fake([
            'email' => 'outsider@example.com',
        ]));

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('filament.admin.auth.login'));

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, [
            'email' => 'outsider@example.com',
        ]);
    }
}
