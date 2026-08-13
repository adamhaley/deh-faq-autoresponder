<?php

namespace App\Filament\Resources\EmailQuestions;

use App\Filament\Resources\EmailQuestions\Pages\ManageEmailQuestions;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Jobs\RetrieveEmailQuestionFaqMatches;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
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
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
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
                    ->options(EmailQuestion::humanReviewDecisionOptions())
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
                    ])
                    ->columnSpanFull(),
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
                Section::make('RAG Context')
                    ->afterHeader([
                        Action::make('retrieveFaqMatches')
                            ->label('Retrieve FAQ matches')
                            ->icon(Heroicon::ArrowPath)
                            ->action(function (EmailQuestion $record): void {
                                $record->update([
                                    'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusQueued,
                                    'faq_retrieval_error' => null,
                                    'faq_retrieval_failed_at' => null,
                                ]);

                                RetrieveEmailQuestionFaqMatches::dispatch($record->id);

                                Notification::make()
                                    ->title('FAQ retrieval queued')
                                    ->body('Matches will appear here after the queue worker finishes.')
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->schema([
                        TextEntry::make('faq_retrieval_status')
                            ->label('Retrieval status')
                            ->badge()
                            ->color(fn (string $state): string => EmailQuestion::faqRetrievalStatusColor($state))
                            ->formatStateUsing(fn (string $state): string => EmailQuestion::faqRetrievalStatusOptions()[$state] ?? $state),
                        TextEntry::make('faq_retrieval_error')
                            ->label('Retrieval error')
                            ->color('danger')
                            ->placeholder('No retrieval error')
                            ->columnSpanFull(),
                        RepeatableEntry::make('faqMatches')
                            ->label('Retrieved FAQ matches')
                            ->placeholder('No FAQ matches retrieved yet.')
                            ->schema([
                                TextEntry::make('rank')
                                    ->label('Rank')
                                    ->badge(),
                                TextEntry::make('similarity')
                                    ->label('Similarity')
                                    ->formatStateUsing(fn (float $state): string => number_format($state * 100, 1).'%')
                                    ->badge()
                                    ->color(fn (float $state): string => $state >= 0.8 ? 'success' : 'warning'),
                                TextEntry::make('faqEntry.question')
                                    ->label('FAQ question')
                                    ->columnSpanFull(),
                                TextEntry::make('faqEntry.answer')
                                    ->label('FAQ answer')
                                    ->columnSpanFull(),
                                TextEntry::make('faqEntry.approvedResponse.approved_response')
                                    ->label('Approved response override')
                                    ->placeholder('No approved response override')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->poll(fn (EmailQuestion $record): ?string => $record->hasActiveFaqRetrieval() ? '3s' : null)
                    ->columnSpanFull(),
                Section::make('Answer Draft')
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make('generateAnswerDraft')
                                ->label('Generate draft answer')
                                ->icon(Heroicon::Sparkles)
                                ->color('primary')
                                ->action(function (EmailQuestion $record): void {
                                    EmailQuestionAnswerDraft::query()->updateOrCreate(
                                        ['email_question_id' => $record->id],
                                        EmailQuestionAnswerDraft::queuedAttributes(),
                                    );

                                    GenerateEmailQuestionAnswerDraft::dispatch($record->id);

                                    Notification::make()
                                        ->title('Draft generation queued')
                                        ->body('The draft will appear here after the queue worker finishes.')
                                        ->success()
                                        ->send();
                                }),
                            Action::make('editFinalAnswer')
                                ->label('Edit final answer')
                                ->icon(Heroicon::PencilSquare)
                                ->schema([
                                    Textarea::make('final_answer')
                                        ->label('Final answer')
                                        ->required()
                                        ->rows(8),
                                ])
                                ->fillForm(fn (EmailQuestion $record): array => [
                                    'final_answer' => $record->answerDraft?->final_answer
                                        ?? $record->answerDraft?->generated_answer
                                        ?? '',
                                ])
                                ->action(function (array $data, EmailQuestion $record): void {
                                    $record->answerDraft?->update([
                                        'final_answer' => $data['final_answer'],
                                    ]);
                                }),
                            Action::make('approveAnswerDraft')
                                ->label('Approve answer')
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusApproved,
                                    auth()->id(),
                                )),
                            Action::make('needsRevisionAnswerDraft')
                                ->label('Needs revision')
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusNeedsRevision,
                                    auth()->id(),
                                )),
                            Action::make('rejectAnswerDraft')
                                ->label('Reject answer')
                                ->icon(Heroicon::XCircle)
                                ->color('danger')
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusRejected,
                                    auth()->id(),
                                )),
                        ])
                            ->label('Draft actions')
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make('answerDraft.status')
                            ->label('Status')
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('status'))
                            ->badge()
                            ->placeholder('No draft generated yet')
                            ->color(fn (?string $state): string => $state === null ? 'gray' : EmailQuestionAnswerDraft::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? 'No draft' : EmailQuestionAnswerDraft::statusOptions()[$state] ?? $state),
                        TextEntry::make('answerDraft.generated_at')
                            ->label('Generated at')
                            ->state(fn (EmailQuestion $record): mixed => $record->answerDraft()->value('generated_at'))
                            ->dateTime()
                            ->placeholder('No draft generated yet'),
                        TextEntry::make('answerDraft.generation_started_at')
                            ->label('Generation started at')
                            ->state(fn (EmailQuestion $record): mixed => $record->answerDraft()->value('generation_started_at'))
                            ->dateTime()
                            ->placeholder('Not started yet'),
                        TextEntry::make('answerDraft.generation_failed_at')
                            ->label('Generation failed at')
                            ->state(fn (EmailQuestion $record): mixed => $record->answerDraft()->value('generation_failed_at'))
                            ->dateTime()
                            ->placeholder('No failure'),
                        TextEntry::make('answerDraft.generation_error')
                            ->label('Generation error')
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('generation_error'))
                            ->color('danger')
                            ->placeholder('No generation error')
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.generated_answer')
                            ->label('Generated answer')
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('generated_answer'))
                            ->placeholder('No draft generated yet')
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.final_answer')
                            ->label('Final answer')
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('final_answer'))
                            ->placeholder('Not edited yet')
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.generation_reason')
                            ->label('Generation reason')
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('generation_reason'))
                            ->placeholder('No generation reason yet')
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.reviewer.name')
                            ->label('Reviewed by')
                            ->placeholder('Not reviewed yet'),
                        TextEntry::make('answerDraft.reviewed_at')
                            ->label('Reviewed at')
                            ->dateTime()
                            ->placeholder('Not reviewed yet'),
                    ])
                    ->poll(fn (EmailQuestion $record): ?string => $record->hasActiveAnswerDraftGeneration() ? '3s' : null)
                    ->columnSpanFull(),
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
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll(fn (): ?string => EmailQuestion::query()->withActiveAsyncPipeline()->exists() ? '3s' : null)
            ->recordTitleAttribute('question_text')
            ->columns([
                TextColumn::make('question_text')
                    ->label('Question')
                    ->searchable()
                    ->limit(80),
                TextColumn::make('faq_matches_count')
                    ->label('RAG')
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => (string) ($state ?? 0))
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray')
                    ->sortable(),
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
                    ->options(EmailQuestion::humanReviewFilterOptions()),
                SelectFilter::make('classification')
                    ->label('AI classification')
                    ->options(EmailQuestion::classificationOptions()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalCancelActionLabel('Close')
                    ->slideOver(),
                EditAction::make()
                    ->slideOver(),
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
            ->with(['answerDraft.reviewer', 'faqMatches.faqEntry.approvedResponse', 'message.mailbox', 'reviewer'])
            ->withCount('faqMatches')
            ->latest();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailQuestions::route('/'),
        ];
    }
}
