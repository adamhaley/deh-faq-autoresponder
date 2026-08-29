<?php

namespace App\Filament\Resources\EmailQuestions;

use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionClassification;
use App\Enums\EmailQuestionReviewStatus;
use App\Enums\FaqRetrievalStatus;
use App\Filament\Resources\EmailQuestions\Pages\ManageEmailQuestions;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Jobs\RetrieveEmailQuestionFaqMatches;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class EmailQuestionResource extends Resource
{
    protected static ?string $model = EmailQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $recordTitleAttribute = 'question_text';

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.email_questions');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.email_question.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.email_question.plural');
    }

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
                    ->label(__('admin.fields.human_review'))
                    ->options(collect(EmailQuestionReviewStatus::reviewerDecisionCases())->mapWithKeys(
                        fn (EmailQuestionReviewStatus $case): array => [$case->value => $case->getLabel()],
                    ))
                    ->required(),
                Select::make('classification')
                    ->label(__('admin.fields.ai_classification'))
                    ->options(EmailQuestionClassification::class)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('classification_confidence')
                    ->label(__('admin.fields.ai_confidence'))
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false),
                Textarea::make('classification_reason')
                    ->label(__('admin.fields.ai_reason'))
                    ->disabled()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.question'))
                    ->schema([
                        TextEntry::make('question_text')
                            ->label(__('admin.fields.extracted_question'))
                            ->extraAttributes(['style' => 'white-space: pre-line;'])
                            ->columnSpanFull(),
                        TextEntry::make('normalized_question')
                            ->placeholder(__('admin.placeholders.not_normalized_yet'))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make(__('admin.sections.human_review'))
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make('markValid')
                                ->label(__('admin.actions.valid_question'))
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn (EmailQuestion $record): bool => $record->markReviewed(
                                    EmailQuestionReviewStatus::Valid,
                                    auth()->id(),
                                )),
                            Action::make('markNoise')
                                ->label(__('admin.actions.noise'))
                                ->icon(Heroicon::XCircle)
                                ->color('gray')
                                ->action(fn (EmailQuestion $record): bool => $record->markReviewed(
                                    EmailQuestionReviewStatus::Noise,
                                    auth()->id(),
                                )),
                            Action::make('markUnanswerable')
                                ->label(__('admin.actions.unanswerable'))
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn (EmailQuestion $record): bool => $record->markReviewed(
                                    EmailQuestionReviewStatus::Unanswerable,
                                    auth()->id(),
                                )),
                        ])
                            ->label(__('admin.actions.classification'))
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make('review_status')
                            ->label(__('admin.fields.classification'))
                            ->badge(),
                        TextEntry::make('reviewer.name')
                            ->label(__('admin.fields.reviewed_by'))
                            ->placeholder(__('admin.placeholders.not_reviewed_yet')),
                        TextEntry::make('reviewed_at')
                            ->dateTime()
                            ->placeholder(__('admin.placeholders.not_reviewed_yet')),
                    ]),
                Section::make(__('admin.sections.ai_prediction'))
                    ->schema([
                        TextEntry::make('classification')
                            ->label(__('admin.fields.ai_classification'))
                            ->badge()
                            ->placeholder(__('admin.statuses.classification.unclassified')),
                        TextEntry::make('classification_confidence')
                            ->label(__('admin.fields.ai_confidence'))
                            ->suffix('%')
                            ->placeholder(__('admin.placeholders.not_classified_yet')),
                        TextEntry::make('classification_reason')
                            ->label(__('admin.fields.ai_reason'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('admin.sections.rag_context'))
                    ->visible(fn (EmailQuestion $record): bool => self::canRunQuestionPipeline($record))
                    ->afterHeader([
                        Action::make('retrieveFaqMatches')
                            ->label(__('admin.actions.retrieve_faq_matches'))
                            ->icon(Heroicon::ArrowPath)
                            ->action(function (EmailQuestion $record): void {
                                $record->update([
                                    'faq_retrieval_status' => FaqRetrievalStatus::Queued,
                                    'faq_retrieval_error' => null,
                                    'faq_retrieval_failed_at' => null,
                                ]);

                                RetrieveEmailQuestionFaqMatches::dispatch($record->id);

                                Notification::make()
                                    ->title(__('admin.notifications.faq_retrieval_queued_title'))
                                    ->body(__('admin.notifications.faq_retrieval_queued_body'))
                                    ->success()
                                    ->send();
                            }),
                    ])
                    ->schema([
                        TextEntry::make('faq_retrieval_status')
                            ->label(__('admin.fields.retrieval_status'))
                            ->badge()
                            ->icon(fn (FaqRetrievalStatus $state): Heroicon|HtmlString|null => self::isActiveFaqRetrievalStatus($state) ? self::spinningStatusIcon() : null),
                        TextEntry::make('faq_retrieval_error')
                            ->label(__('admin.fields.retrieval_error'))
                            ->color('danger')
                            ->placeholder(__('admin.placeholders.no_retrieval_error'))
                            ->columnSpanFull(),
                        RepeatableEntry::make('faqMatches')
                            ->label(__('admin.fields.rag'))
                            ->placeholder(__('admin.placeholders.no_faq_matches'))
                            ->schema([
                                TextEntry::make('rank')
                                    ->label(__('admin.fields.rank'))
                                    ->badge(),
                                TextEntry::make('similarity')
                                    ->label(__('admin.fields.similarity'))
                                    ->formatStateUsing(fn (float $state): string => number_format($state * 100, 1).'%')
                                    ->badge()
                                    ->color(fn (float $state): string => $state >= 0.8 ? 'success' : 'warning'),
                                TextEntry::make('faqEntry.question')
                                    ->label(__('admin.fields.faq_question'))
                                    ->columnSpanFull(),
                                TextEntry::make('faqEntry.answer')
                                    ->label(__('admin.fields.faq_answer'))
                                    ->columnSpanFull(),
                                TextEntry::make('faqEntry.approvedResponse.approved_response')
                                    ->label(__('admin.fields.approved_response_override'))
                                    ->placeholder(__('admin.placeholders.no_approved_response_override'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ])
                    ->poll(fn (EmailQuestion $record): ?string => $record->hasActiveFaqRetrieval() ? '3s' : null)
                    ->columnSpanFull(),
                Section::make(__('admin.sections.answer_draft'))
                    ->visible(fn (EmailQuestion $record): bool => self::canRunQuestionPipeline($record))
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make('generateAnswerDraft')
                                ->label(__('admin.actions.generate_draft_answer'))
                                ->icon(Heroicon::Sparkles)
                                ->color('primary')
                                ->action(function (EmailQuestion $record): void {
                                    EmailQuestionAnswerDraft::query()->updateOrCreate(
                                        ['email_question_id' => $record->id],
                                        EmailQuestionAnswerDraft::queuedAttributes(),
                                    );

                                    GenerateEmailQuestionAnswerDraft::dispatch($record->id);

                                    Notification::make()
                                        ->title(__('admin.notifications.draft_generation_queued_title'))
                                        ->body(__('admin.notifications.draft_generation_queued_body'))
                                        ->success()
                                        ->send();
                                }),
                            Action::make('editFinalAnswer')
                                ->label(__('admin.actions.edit_final_answer'))
                                ->icon(Heroicon::PencilSquare)
                                ->schema([
                                    Textarea::make('final_answer')
                                        ->label(__('admin.fields.final_answer'))
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
                                    $record->answerDraft?->syncApprovedSideEffects();
                                }),
                            Action::make('approveAnswerDraft')
                                ->label(__('admin.actions.approve_answer'))
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    AnswerDraftStatus::Approved,
                                    auth()->id(),
                                )),
                            Action::make('needsRevisionAnswerDraft')
                                ->label(__('admin.actions.needs_revision'))
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    AnswerDraftStatus::NeedsRevision,
                                    auth()->id(),
                                )),
                            Action::make('rejectAnswerDraft')
                                ->label(__('admin.actions.reject_answer'))
                                ->icon(Heroicon::XCircle)
                                ->color('danger')
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    AnswerDraftStatus::Rejected,
                                    auth()->id(),
                                )),
                        ])
                            ->label(__('admin.actions.draft_actions'))
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make('answerDraft.status')
                            ->label(__('admin.fields.status'))
                            ->state(fn (EmailQuestion $record): ?AnswerDraftStatus => $record->answerDraft()->value('status'))
                            ->badge()
                            ->icon(fn (?AnswerDraftStatus $state): Heroicon|HtmlString|null => self::isActiveAnswerDraftStatus($state) ? self::spinningStatusIcon() : null)
                            ->placeholder(__('admin.placeholders.no_draft_generated_yet')),
                        TextEntry::make('answerDraft.generated_at')
                            ->label(__('admin.fields.generated_at'))
                            ->state(fn (EmailQuestion $record): mixed => $record->answerDraft()->value('generated_at'))
                            ->dateTime()
                            ->placeholder(__('admin.placeholders.no_draft_generated_yet')),
                        TextEntry::make('answerDraft.generation_started_at')
                            ->label(__('admin.fields.generation_started_at'))
                            ->state(fn (EmailQuestion $record): mixed => $record->answerDraft()->value('generation_started_at'))
                            ->dateTime()
                            ->placeholder(__('admin.placeholders.not_started_yet')),
                        TextEntry::make('answerDraft.generation_failed_at')
                            ->label(__('admin.fields.generation_failed_at'))
                            ->state(fn (EmailQuestion $record): mixed => $record->answerDraft()->value('generation_failed_at'))
                            ->dateTime()
                            ->placeholder(__('admin.placeholders.no_failure')),
                        TextEntry::make('answerDraft.generation_error')
                            ->label(__('admin.fields.generation_error'))
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('generation_error'))
                            ->color('danger')
                            ->placeholder(__('admin.placeholders.no_generation_error'))
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.generated_answer')
                            ->label(__('admin.fields.generated_answer'))
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('generated_answer'))
                            ->placeholder(__('admin.placeholders.no_draft_generated_yet'))
                            ->extraAttributes(['style' => 'white-space: pre-line;'])
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.final_answer')
                            ->label(__('admin.fields.final_answer'))
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('final_answer'))
                            ->placeholder(__('admin.placeholders.not_edited_yet'))
                            ->extraAttributes(['style' => 'white-space: pre-line;'])
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.generation_reason')
                            ->label(__('admin.fields.generation_reason'))
                            ->state(fn (EmailQuestion $record): ?string => $record->answerDraft()->value('generation_reason'))
                            ->placeholder(__('admin.placeholders.no_generation_reason'))
                            ->columnSpanFull(),
                        TextEntry::make('answerDraft.reviewer.name')
                            ->label(__('admin.fields.reviewed_by'))
                            ->placeholder(__('admin.placeholders.not_reviewed_yet')),
                        TextEntry::make('answerDraft.reviewed_at')
                            ->label(__('admin.fields.reviewed_at'))
                            ->dateTime()
                            ->placeholder(__('admin.placeholders.not_reviewed_yet')),
                        SchemaActions::make([
                            Action::make('quickEditFinalAnswer')
                                ->label(__('admin.actions.edit_final_answer'))
                                ->icon(Heroicon::PencilSquare)
                                ->color('gray')
                                ->outlined()
                                ->schema([
                                    Textarea::make('final_answer')
                                        ->label(__('admin.fields.final_answer'))
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
                                    $record->answerDraft?->syncApprovedSideEffects();
                                }),
                            Action::make('quickApproveAnswerDraft')
                                ->label(__('admin.actions.approve'))
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->outlined()
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    AnswerDraftStatus::Approved,
                                    auth()->id(),
                                )),
                            Action::make('quickNeedsRevisionAnswerDraft')
                                ->label(__('admin.actions.needs_revision'))
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->outlined()
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    AnswerDraftStatus::NeedsRevision,
                                    auth()->id(),
                                )),
                            Action::make('quickRejectAnswerDraft')
                                ->label(__('admin.actions.reject'))
                                ->icon(Heroicon::XCircle)
                                ->color('danger')
                                ->outlined()
                                ->action(fn (EmailQuestion $record): ?bool => $record->answerDraft?->markReviewed(
                                    AnswerDraftStatus::Rejected,
                                    auth()->id(),
                                )),
                        ])
                            ->alignment(Alignment::Start)
                            ->columnSpanFull(),
                    ])
                    ->poll(fn (EmailQuestion $record): ?string => $record->hasActiveAnswerDraftGeneration() ? '3s' : null)
                    ->columnSpanFull(),
                Section::make(__('admin.sections.source_email'))
                    ->schema([
                        TextEntry::make('message.mailbox.email')
                            ->label(__('admin.fields.mailbox')),
                        TextEntry::make('message.participant_name')
                            ->label(__('admin.fields.participant'))
                            ->placeholder(__('admin.placeholders.unknown')),
                        TextEntry::make('message.reply_to_email')
                            ->label(__('admin.fields.email_address'))
                            ->placeholder(__('admin.placeholders.unknown')),
                        TextEntry::make('message.from_email')
                            ->label(__('admin.fields.from')),
                        TextEntry::make('message.subject')
                            ->label(__('admin.fields.subject'))
                            ->columnSpanFull(),
                        TextEntry::make('message.snippet')
                            ->label(__('admin.fields.snippet'))
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
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('review_status')
                    ->label(__('admin.fields.human_review'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('classification')
                    ->label(__('admin.fields.ai_classification'))
                    ->badge()
                    ->placeholder(__('admin.statuses.classification.unclassified'))
                    ->sortable(),
                TextColumn::make('question_text')
                    ->label(__('admin.fields.question'))
                    ->searchable()
                    ->limit(80),
                TextColumn::make('faq_matches_count')
                    ->label(__('admin.fields.rag'))
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => (string) ($state ?? 0))
                    ->color(fn (?int $state): string => ($state ?? 0) > 0 ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('message.participant_name')
                    ->label(__('admin.fields.participant'))
                    ->formatStateUsing(fn (?string $state, EmailQuestion $record): ?string => $state ?? $record->message?->reply_to_email)
                    ->placeholder(__('admin.placeholders.unknown'))
                    ->searchable(['participant_name', 'reply_to_email']),
                TextColumn::make('classification_confidence')
                    ->label(__('admin.fields.ai_confidence'))
                    ->suffix('%')
                    ->sortable(),
                IconColumn::make('reviewed_at')
                    ->label(__('admin.fields.reviewed'))
                    ->boolean()
                    ->state(fn (EmailQuestion $record): bool => $record->reviewed_at !== null),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('review_status')
                    ->label(__('admin.fields.human_review'))
                    ->options(collect(EmailQuestionReviewStatus::reviewerFilterCases())->mapWithKeys(
                        fn (EmailQuestionReviewStatus $case): array => [$case->value => $case->getLabel()],
                    )),
                SelectFilter::make('classification')
                    ->label(__('admin.fields.ai_classification'))
                    ->options(EmailQuestionClassification::class),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalCancelActionLabel(__('admin.actions.close'))
                    ->slideOver(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['answerDraft.reviewer', 'faqMatches.faqEntry.approvedResponse', 'message.mailbox', 'reviewer'])
            ->withCount('faqMatches');
    }

    private static function canRunQuestionPipeline(EmailQuestion $record): bool
    {
        return $record->fresh()->review_status === EmailQuestionReviewStatus::Valid;
    }

    private static function isActiveFaqRetrievalStatus(FaqRetrievalStatus $status): bool
    {
        return in_array($status, [
            FaqRetrievalStatus::Queued,
            FaqRetrievalStatus::Processing,
        ], true);
    }

    private static function isActiveAnswerDraftStatus(?AnswerDraftStatus $status): bool
    {
        return in_array($status, [
            AnswerDraftStatus::Queued,
            AnswerDraftStatus::Generating,
        ], true);
    }

    private static function spinningStatusIcon(): HtmlString
    {
        return new HtmlString(<<<'HTML'
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="none"
                aria-hidden="true"
                style="animation: deh-status-spin 1s linear infinite;"
            >
                <style>
                    @keyframes deh-status-spin {
                        from { transform: rotate(0deg); }
                        to { transform: rotate(360deg); }
                    }
                </style>
                <path
                    d="M10 3a7 7 0 1 1-6.33 4.01"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
                <path
                    d="M3.25 3.75v3.5h3.5"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
            HTML);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailQuestions::route('/'),
        ];
    }
}
