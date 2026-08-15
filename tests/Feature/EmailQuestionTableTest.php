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
            ->assertTableActionsExistInOrder(['view', 'delete'])
            ->assertTableActionDoesNotExist('edit');

        $columnNames = array_values(array_map(
            fn ($column): string => $column->getName(),
            $component->instance()->getTable()->getColumns(),
        ));

        $this->assertSame(['review_status', 'classification', 'question_text'], array_slice($columnNames, 0, 3));
    }
}
