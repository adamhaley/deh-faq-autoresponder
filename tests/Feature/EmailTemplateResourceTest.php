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

    public function test_duplicating_a_template_gives_the_chosen_user_a_personal_copy(): void
    {
        $default = EmailTemplate::factory()->create(['subject' => 'Live subject', 'body' => '<p>Live body</p>']);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $reviewer = User::factory()->create(['name' => 'Anna']);
        $this->actingAs($admin);

        Livewire::test(ManageEmailTemplates::class)
            ->callTableAction('duplicate', $default, ['user_id' => $reviewer->id, 'name' => 'Anna'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('email_templates', [
            'user_id' => $reviewer->id,
            'name' => 'Anna',
            'subject' => 'Live subject',
            'body' => '<p>Live body</p>',
        ]);
    }

    public function test_duplicating_a_template_leaves_the_original_untouched(): void
    {
        $default = EmailTemplate::factory()->create(['user_id' => null, 'name' => 'Default']);
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $reviewer = User::factory()->create();
        $this->actingAs($admin);

        Livewire::test(ManageEmailTemplates::class)
            ->callTableAction('duplicate', $default, ['user_id' => $reviewer->id, 'name' => 'Copy']);

        $this->assertDatabaseHas('email_templates', ['id' => $default->id, 'user_id' => null, 'name' => 'Default']);
    }

    public function test_a_user_who_already_has_a_personal_template_cannot_be_given_a_second(): void
    {
        $default = EmailTemplate::factory()->create();
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $reviewer = User::factory()->create();
        EmailTemplate::factory()->create(['user_id' => $reviewer->id]);
        $this->actingAs($admin);

        Livewire::test(ManageEmailTemplates::class)
            ->callTableAction('duplicate', $default, ['user_id' => $reviewer->id, 'name' => 'Second'])
            ->assertHasTableActionErrors(['user_id']);
    }

    public function test_only_admins_can_duplicate_a_template(): void
    {
        $default = EmailTemplate::factory()->create();
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $this->actingAs($reviewer);

        Livewire::test(ManageEmailTemplates::class)
            ->assertTableActionHidden('duplicate', $default);
    }

    public function test_the_table_identifies_a_personal_template_owner_by_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $owner = User::factory()->create(['name' => 'Anna Streeb', 'email' => 'anna@example.com']);
        $template = EmailTemplate::factory()->create(['user_id' => $owner->id]);
        $this->actingAs($admin);

        Livewire::test(ManageEmailTemplates::class)
            ->assertTableColumnStateSet('user.email', 'anna@example.com', $template);
    }
}
