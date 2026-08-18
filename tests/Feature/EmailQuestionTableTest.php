<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\EmailQuestions\Pages\ManageEmailQuestions;
use App\Models\EmailQuestion;
use App\Models\GmailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmailQuestionTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_table_leads_with_classification_columns_and_has_no_edit_action(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
        $message = GmailMessage::factory()->create([
            'from_email' => 'notifications@webinaris.co',
            'participant_name' => 'Helmut Kempf',
            'reply_to_email' => 'kempf-helmut@example.com',
        ]);
        $question = EmailQuestion::factory()
            ->for($message, 'message')
            ->classifiedAsValid()
            ->create();

        $this->actingAs($admin);

        $component = Livewire::test(ManageEmailQuestions::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$question])
            ->assertTableColumnExists('review_status')
            ->assertTableColumnExists('classification')
            ->assertTableColumnExists('question_text')
            ->assertTableColumnExists('message.participant_name')
            ->assertTableColumnDoesNotExist('message.from_email')
            ->assertTableColumnFormattedStateSet('message.participant_name', 'Helmut Kempf', record: $question)
            ->assertTableActionsExistInOrder(['view'])
            ->assertTableActionDoesNotExist('delete')
            ->assertTableActionDoesNotExist('edit')
            ->mountTableAction('view', $question)
            ->assertHasNoActionErrors()
            ->assertMountedActionModalSee('Helmut Kempf')
            ->assertMountedActionModalSee('kempf-helmut@example.com');

        $columnNames = array_values(array_map(
            fn ($column): string => $column->getName(),
            $component->instance()->getTable()->getColumns(),
        ));

        $this->assertSame(['review_status', 'classification', 'question_text'], array_slice($columnNames, 0, 3));
    }

    public function test_the_table_can_be_sorted_by_a_column_other_than_created_at(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $lowConfidence = EmailQuestion::factory()
            ->for(GmailMessage::factory(), 'message')
            ->create(['classification_confidence' => 10, 'created_at' => now()->subMinute()]);
        $highConfidence = EmailQuestion::factory()
            ->for(GmailMessage::factory(), 'message')
            ->create(['classification_confidence' => 90, 'created_at' => now()]);

        $this->actingAs($admin);

        Livewire::test(ManageEmailQuestions::class)
            ->assertOk()
            ->sortTable('classification_confidence')
            ->assertCanSeeTableRecords([$lowConfidence, $highConfidence], inOrder: true)
            ->sortTable('classification_confidence', 'desc')
            ->assertCanSeeTableRecords([$highConfidence, $lowConfidence], inOrder: true);
    }

    public function test_invalid_reviewed_questions_hide_downstream_pipeline_sections(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);

        $this->actingAs($admin);

        foreach ([EmailQuestion::ReviewStatusNoise, EmailQuestion::ReviewStatusUnanswerable] as $reviewStatus) {
            $question = EmailQuestion::factory()
                ->for(GmailMessage::factory(), 'message')
                ->reviewedAs($reviewStatus)
                ->create();

            Livewire::test(ManageEmailQuestions::class)
                ->mountTableAction('view', $question)
                ->assertOk()
                ->assertHasNoActionErrors()
                ->assertMountedActionModalSee('Human Review')
                ->assertMountedActionModalDontSee('RAG Context')
                ->assertMountedActionModalDontSee('Retrieve FAQ matches')
                ->assertMountedActionModalDontSee('Answer Draft')
                ->assertMountedActionModalDontSee('Generate draft answer');
        }
    }
}
