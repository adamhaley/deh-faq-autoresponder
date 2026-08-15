<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EmailQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class AdminLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_uses_german_labels_for_german_browser_language(): void
    {
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('Mit Google fortfahren')
            ->assertDontSee('Continue with Google');
    }

    public function test_admin_login_uses_english_labels_for_english_browser_language(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9,de;q=0.8')
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertDontSee('Mit Google fortfahren');
    }

    public function test_status_options_are_translated_for_the_current_locale(): void
    {
        App::setLocale('de');

        $this->assertSame(
            'Gültige Frage',
            EmailQuestion::reviewStatusOptions()[EmailQuestion::ReviewStatusValid],
        );

        App::setLocale('en');

        $this->assertSame(
            'Valid question',
            EmailQuestion::reviewStatusOptions()[EmailQuestion::ReviewStatusValid],
        );
    }

    public function test_resource_page_heading_uses_german_browser_language(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8')
            ->get('/admin/email-questions')
            ->assertOk()
            ->assertSee('E-Mail-Fragen')
            ->assertDontSee('Email Questions');
    }
}
