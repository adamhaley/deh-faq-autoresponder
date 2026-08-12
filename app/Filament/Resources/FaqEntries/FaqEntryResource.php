<?php

namespace App\Filament\Resources\FaqEntries;

use App\Filament\Resources\FaqEntries\Pages\ManageFaqEntries;
use App\Models\FaqEntry;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqEntryResource extends Resource
{
    protected static ?string $model = FaqEntry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('question')
                ->required()
                ->rows(4),
            Textarea::make('answer')
                ->required()
                ->rows(8),
            Textarea::make('embedding')
                ->rows(4)
                ->helperText('Stored as vector(1536) on Postgres; leave blank for manual entries until embedding generation is wired.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')->searchable()->limit(80),
                TextColumn::make('answer')->limit(120),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaqEntries::route('/'),
        ];
    }
}
