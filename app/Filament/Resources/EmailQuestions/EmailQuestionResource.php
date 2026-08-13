<?php

namespace App\Filament\Resources\EmailQuestions;

use App\Filament\Resources\EmailQuestions\Pages\ManageEmailQuestions;
use App\Models\EmailQuestion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailQuestionResource extends Resource
{
    protected static ?string $model = EmailQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'Email Questions';

    protected static ?string $recordTitleAttribute = 'question_text';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('question_text')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
                Textarea::make('normalized_question')
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('review_status')
                    ->label('Human Review')
                    ->options(EmailQuestion::reviewStatusOptions())
                    ->required(),
                Select::make('classification')
                    ->label('AI Classification')
                    ->options(EmailQuestion::classificationOptions())
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('classification_confidence')
                    ->label('AI Confidence')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('classification_reason')
                    ->label('AI Reason')
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Question')
                    ->schema([
                        TextEntry::make('question_text')
                            ->label('Extracted question')
                            ->columnSpanFull(),
                        TextEntry::make('normalized_question')
                            ->placeholder('Not normalized yet')
                            ->columnSpanFull(),
                    ]),
                Section::make('Human Review')
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make('markValid')
                                ->label('Valid question')
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn (EmailQuestion $record): bool => $record->markReviewed(
                                    EmailQuestion::ReviewStatusValid,
                                    auth()->id(),
                                )),
                            Action::make('markNoise')
                                ->label('noise')
                                ->icon(Heroicon::XCircle)
                                ->color('gray')
                                ->action(fn (EmailQuestion $record): bool => $record->markReviewed(
                                    EmailQuestion::ReviewStatusNoise,
                                    auth()->id(),
                                )),
                            Action::make('markUnanswerable')
                                ->label('unanswerable')
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn (EmailQuestion $record): bool => $record->markReviewed(
                                    EmailQuestion::ReviewStatusUnanswerable,
                                    auth()->id(),
                                )),
                        ])
                            ->label('Classification')
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make('review_status')
                            ->label('Classification')
                            ->badge()
                            ->color(fn (string $state): string => EmailQuestion::reviewStatusColor($state))
                            ->formatStateUsing(fn (string $state): string => EmailQuestion::reviewStatusOptions()[$state] ?? $state),
                        TextEntry::make('reviewer.name')
                            ->label('Reviewed by')
                            ->placeholder('Not reviewed yet'),
                        TextEntry::make('reviewed_at')
                            ->dateTime()
                            ->placeholder('Not reviewed yet'),
                    ]),
                Section::make('AI Prediction')
                    ->schema([
                        TextEntry::make('classification')
                            ->label('AI Classification')
                            ->badge()
                            ->color(fn (?string $state): string => EmailQuestion::classificationColor($state))
                            ->formatStateUsing(fn (?string $state): string => EmailQuestion::classificationOptions()[$state] ?? 'Unclassified'),
                        TextEntry::make('classification_confidence')
                            ->label('AI Confidence')
                            ->suffix('%')
                            ->placeholder('Not classified yet'),
                        TextEntry::make('classification_reason')
                            ->label('AI Reason')
                            ->columnSpanFull(),
                    ]),
                Section::make('Source email')
                    ->schema([
                        TextEntry::make('message.mailbox.email')
                            ->label('Mailbox'),
                        TextEntry::make('message.from_email')
                            ->label('From'),
                        TextEntry::make('message.subject')
                            ->label('Subject')
                            ->columnSpanFull(),
                        TextEntry::make('message.snippet')
                            ->label('Snippet')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_text')
                    ->label('Question')
                    ->searchable()
                    ->limit(80),
                TextColumn::make('message.from_email')
                    ->label('From')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('review_status')
                    ->label('Human Review')
                    ->badge()
                    ->color(fn (string $state): string => EmailQuestion::reviewStatusColor($state))
                    ->formatStateUsing(fn (string $state): string => EmailQuestion::reviewStatusOptions()[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('classification')
                    ->label('AI Classification')
                    ->badge()
                    ->color(fn (?string $state): string => EmailQuestion::classificationColor($state))
                    ->formatStateUsing(fn (?string $state): string => EmailQuestion::classificationOptions()[$state] ?? 'Unclassified')
                    ->sortable(),
                TextColumn::make('classification_confidence')
                    ->label('AI Confidence')
                    ->suffix('%')
                    ->sortable(),
                IconColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->boolean()
                    ->state(fn (EmailQuestion $record): bool => $record->reviewed_at !== null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_status')
                    ->label('Human classification')
                    ->options(EmailQuestion::reviewStatusOptions()),
                SelectFilter::make('classification')
                    ->label('AI classification')
                    ->options(EmailQuestion::classificationOptions()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalCancelActionLabel('Close'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['message.mailbox', 'reviewer'])
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailQuestions::route('/'),
        ];
    }
}
