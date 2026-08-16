<?php

namespace App\Filament\Resources\FaqApprovedResponses;

use App\Filament\Resources\FaqApprovedResponses\Pages\ManageFaqApprovedResponses;
use App\Models\FaqApprovedResponse;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaqApprovedResponseResource extends Resource
{
    protected static ?string $model = FaqApprovedResponse::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static ?string $recordTitleAttribute = 'approved_response';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.faq_approved_responses');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.faq_approved_response.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.faq_approved_response.plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('faqEntry.question')
                ->label(__('admin.fields.faq'))
                ->columnSpanFull(),
            TextEntry::make('approved_response')
                ->label(__('admin.fields.approved_response_override'))
                ->columnSpanFull(),
            TextEntry::make('match_similarity')
                ->label(__('admin.fields.similarity'))
                ->numeric(),
            TextEntry::make('updated_at')
                ->label(__('admin.fields.updated_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('faqEntry.question')->label(__('admin.fields.faq'))->searchable()->limit(80),
                TextColumn::make('approved_response')->limit(120),
                TextColumn::make('match_similarity')->numeric(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(__('admin.actions.view_faq_approved_response')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFaqApprovedResponses::route('/'),
        ];
    }
}
