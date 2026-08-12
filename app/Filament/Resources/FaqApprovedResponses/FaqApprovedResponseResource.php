<?php

namespace App\Filament\Resources\FaqApprovedResponses;

use App\Filament\Resources\FaqApprovedResponses\Pages\ManageFaqApprovedResponses;
use App\Models\FaqApprovedResponse;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqApprovedResponseResource extends Resource
{
    protected static ?string $model = FaqApprovedResponse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $recordTitleAttribute = 'approved_response';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('faq_entry_id')
                ->relationship('faqEntry', 'question')
                ->searchable()
                ->preload()
                ->required(),
            Textarea::make('approved_response')
                ->required()
                ->rows(8),
            TextInput::make('match_similarity')
                ->numeric(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('faqEntry.question')->label('FAQ')->searchable()->limit(80),
                TextColumn::make('approved_response')->limit(120),
                TextColumn::make('match_similarity')->numeric(),
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
            'index' => ManageFaqApprovedResponses::route('/'),
        ];
    }
}
