<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\EmailTemplates\Pages\ManageEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_create_form_defaults_the_owner_to_the_logged_in_user(): void
    {
        EmailTemplate::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($admin);

        Livewire::test(ManageEmailTemplates::class)
            ->mountTableAction('create')
            ->assertTableActionDataSet(['user_id' => $admin->id]);
    }

    public function test_the_owner_can_be_cleared_to_create_a_shared_template(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($admin);

        Livewire::test(ManageEmailTemplates::class)
            ->callTableAction('create', null, [
                'name' => 'Fallback',
                'user_id' => null,
                'subject' => 'Subject',
                'body' => 'Body',
            ])
            ->assertHasNoTableActionErrors();

        $template = EmailTemplate::query()->firstWhere('name', 'Fallback');

        $this->assertNotNull($template);
        $this->assertNull($template->user_id);
    }
}
