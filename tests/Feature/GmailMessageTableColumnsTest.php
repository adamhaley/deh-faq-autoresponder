<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Resources\GmailMessages\Pages\ManageGmailMessages;
use App\Models\User;
use Filament\Tables\Columns\Column;
use Generator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GmailMessageTableColumnsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return Generator<string, array{string}>
     */
    public static function dataColumnProvider(): Generator
    {
        yield 'id' => ['id'];
        yield 'participant' => ['participant_name'];
        yield 'questions' => ['questions_count'];
        yield 'mailbox' => ['mailbox.email'];
        yield 'from' => ['from_email'];
        yield 'subject' => ['subject'];
        yield 'snippet' => ['snippet'];
        yield 'received' => ['internal_date'];
        yield 'imported at' => ['imported_at'];
    }

    #[DataProvider('dataColumnProvider')]
    public function test_every_data_column_can_be_hidden_from_the_column_manager(string $column): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer, 'is_active' => true]);
        $this->actingAs($reviewer);

        Livewire::test(ManageGmailMessages::class)
            ->toggleAllTableColumns()
            ->assertTableColumnExists($column, fn (Column $column): bool => $column->isToggleable());
    }
}
