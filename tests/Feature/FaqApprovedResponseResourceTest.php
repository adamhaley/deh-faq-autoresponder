<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FaqApprovedResponses\Pages\ManageFaqApprovedResponses;
use App\Models\FaqApprovedResponse;
use App\Models\FaqEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FaqApprovedResponseResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_view_but_not_manually_mutate_faq_approved_responses(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $faqEntry = FaqEntry::factory()->create(['question' => 'Dispokredit sicher?']);
        $approvedResponse = FaqApprovedResponse::query()->create([
            'faq_entry_id' => $faqEntry->id,
            'approved_response' => 'Vielen Dank für Ihre Rückmeldung. Wenn für Sie alles in Ordnung ist, besteht von Ihrer Seite aus aktuell kein weiterer Handlungsbedarf.',
            'match_similarity' => 0.38,
        ]);

        $this->actingAs($admin);

        Livewire::test(ManageFaqApprovedResponses::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$approvedResponse])
            ->assertTableActionsExistInOrder(['view'])
            ->assertTableActionDoesNotExist('edit')
            ->assertTableActionDoesNotExist('delete')
            ->assertActionDoesNotExist('create')
            ->mountTableAction('view', $approvedResponse)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('View FAQ approved response')
            ->assertMountedActionModalSee('Dispokredit sicher?')
            ->assertMountedActionModalSee('Vielen Dank für Ihre Rückmeldung.');
    }
}
