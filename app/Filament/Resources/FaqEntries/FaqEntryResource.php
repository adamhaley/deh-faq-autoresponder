<?php

namespace App\Filament\Resources\FaqEntries;

use App\Filament\Resources\FaqEntries\Pages\ManageFaqEntries;
use App\Models\FaqEntry;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqEntryResource extends Resource
{
    protected static ?string $model = FaqEntry::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $recordTitleAttribute = 'question';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.faq_entries');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('question')
                ->columnSpanFull(),
            TextEntry::make('answer')
                ->columnSpanFull(),
            TextEntry::make('embedding')
                ->label(__('admin.fields.embedding'))
                ->placeholder(__('admin.placeholders.no_embedding_stored'))
                ->limit(160)
                ->columnSpanFull(),
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
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaqEntries::route('/'),
        ];
    }
}
