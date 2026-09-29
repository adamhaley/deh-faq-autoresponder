<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\FaqEntries\Pages\ManageFaqEntries;
use App\Models\FaqEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;
use Tests\TestCase;

class FaqEntryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_a_faq_entry_with_a_generated_embedding(): void
    {
        Embeddings::fake([[$this->embedding([1.0, 0.0])]]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $this->actingAs($admin);

        Livewire::test(ManageFaqEntries::class)
            ->callTableAction('create', null, [
                'question' => 'Ab welcher Summe kann ich investieren?',
                'answer' => 'Ab 15.000 Euro.',
            ])
            ->assertHasNoTableActionErrors();

        $entry = FaqEntry::query()->firstWhere('question', 'Ab welcher Summe kann ich investieren?');

        $this->assertNotNull($entry);
        $this->assertSame('Ab 15.000 Euro.', $entry->answer);
        $this->assertSame('['.implode(',', $this->embedding([1.0, 0.0])).']', $entry->embedding);
    }

    public function test_an_admin_can_edit_a_faq_entry_and_its_embedding_regenerates(): void
    {
        Embeddings::fake([[$this->embedding([0.0, 1.0])]]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $entry = FaqEntry::factory()->create(['question' => 'Old question?']);

        $this->actingAs($admin);

        Livewire::test(ManageFaqEntries::class)
            ->callTableAction('edit', $entry, [
                'question' => 'New question?',
                'answer' => 'New answer.',
            ])
            ->assertHasNoTableActionErrors();

        $entry->refresh();

        $this->assertSame('New question?', $entry->question);
        $this->assertSame('['.implode(',', $this->embedding([0.0, 1.0])).']', $entry->embedding);
    }

    public function test_a_reviewer_cannot_create_edit_or_delete_faq_entries(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $entry = FaqEntry::factory()->create();

        $this->assertFalse($reviewer->can('create', FaqEntry::class));
        $this->assertFalse($reviewer->can('update', $entry));
        $this->assertFalse($reviewer->can('delete', $entry));
    }

    /**
     * @param  list<float>  $prefix
     * @return list<float>
     */
    private function embedding(array $prefix): array
    {
        return array_pad($prefix, 1536, 0.0);
    }
}
