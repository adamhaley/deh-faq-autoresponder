<?php

namespace App\Filament\Resources\GmailMessages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Resources\GmailMessages\Pages\ManageGmailMessages;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\EmailTemplate;
use App\Models\EmailThreadDraft;
use App\Models\GmailMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Component;
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
use Illuminate\Support\Str;

class GmailMessageResource extends Resource
{
    protected static ?string $model = GmailMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?int $navigationSort = -2;

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.webinar_responses');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.webinar_response.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.webinar_response.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mailbox_email')
                    ->label(__('admin.fields.mailbox'))
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('from_email')
                    ->label(__('admin.fields.from'))
                    ->disabled(),
                TextInput::make('subject')
                    ->disabled(),
                TextInput::make('internal_date')
                    ->disabled(),
                Textarea::make('snippet')
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('text_body')
                    ->label(__('admin.fields.text_body'))
                    ->readOnly()
                    ->columnSpanFull(),
                Textarea::make('html_body')
                    ->label(__('admin.fields.html_body'))
                    ->readOnly()
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('admin.sections.message'))
                    ->schema([
                        TextEntry::make('participant_name')->label(__('admin.fields.participant'))->placeholder(__('admin.placeholders.unknown')),
                        TextEntry::make('reply_to_email')->label(__('admin.fields.email_address')),
                        TextEntry::make('internal_date')->label(__('admin.fields.received'))->dateTime(),
                        TextEntry::make('snippet')
                            ->label(__('admin.fields.preview'))
                            ->placeholder(__('admin.placeholders.no_preview_available'))
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make(__('admin.sections.questions'))
                    ->schema(fn (GmailMessage $record): array => self::questionComponents($record))
                    ->poll(fn (GmailMessage $record): ?string => $record->questions()->get()->contains(
                        fn (EmailQuestion $question): bool => $question->hasActiveAsyncPipeline(),
                    ) ? '3s' : null)
                    ->columnSpanFull(),
                Section::make(__('admin.sections.composed_email'))
                    ->afterHeader([
                        Action::make('editTemplate')
                            ->label(__('admin.actions.edit_template'))
                            ->icon(Heroicon::PencilSquare)
                            ->color('gray')
                            ->link()
                            ->schema(fn (Schema $schema): Schema => EmailTemplateResource::form($schema))
                            ->fillForm(fn (): array => EmailTemplate::query()->first()?->toArray() ?? [])
                            ->action(function (array $data): void {
                                EmailTemplate::query()->first()?->update($data);
                            })
                            ->visible(fn (): bool => auth()->user()?->can('update', EmailTemplate::query()->first() ?? new EmailTemplate) ?? false),
                    ])
                    ->schema(fn (GmailMessage $record): array => self::threadDraftComponents($record))
                    ->poll(fn (GmailMessage $record): ?string => self::composedEmailNeedsPolling($record) ? '3s' : null)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<Component>
     */
    private static function questionComponents(GmailMessage $record): array
    {
        $questions = $record->questions()
            ->with(['answerDraft', 'faqMatches.faqEntry'])
            ->orderBy('question_order')
            ->get();

        if ($questions->isEmpty()) {
            return [
                TextEntry::make('no_questions')
                    ->label('')
                    ->state(__('admin.placeholders.no_questions_extracted')),
            ];
        }

        return $questions
            ->map(fn (EmailQuestion $question, int $index): Component => self::questionSection($question, $index + 1))
            ->all();
    }

    private static function questionSection(EmailQuestion $question, int $number): Component
    {
        return Section::make("{$number}. ".Str::limit($question->question_text, 80))
            ->key("question_{$question->id}")
            ->collapsible()
            ->collapsed()
            ->schema([
                TextEntry::make("question_{$question->id}_status")
                    ->label('')
                    ->state(fn (): string => sprintf(
                        '%s · %s',
                        EmailQuestion::reviewStatusOptions()[$question->fresh()->review_status] ?? $question->review_status,
                        $question->fresh()->answerDraft?->status
                            ? (EmailQuestionAnswerDraft::statusOptions()[$question->fresh()->answerDraft->status] ?? $question->fresh()->answerDraft->status)
                            : __('admin.placeholders.no_draft'),
                    ))
                    ->badge()
                    ->color(fn (): string => EmailQuestion::reviewStatusColor($question->fresh()->review_status)),
                TextEntry::make("question_{$question->id}_text")
                    ->label(__('admin.fields.question'))
                    ->state($question->question_text)
                    ->columnSpanFull(),
                Section::make(__('admin.sections.review'))
                    ->key("review_{$question->id}")
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make("markValid_{$question->id}")
                                ->label(__('admin.actions.valid_question'))
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn () => $question->markReviewed(EmailQuestion::ReviewStatusValid, auth()->id())),
                            Action::make("markNoise_{$question->id}")
                                ->label(__('admin.actions.noise'))
                                ->icon(Heroicon::XCircle)
                                ->color('gray')
                                ->action(fn () => $question->markReviewed(EmailQuestion::ReviewStatusNoise, auth()->id())),
                            Action::make("markUnanswerable_{$question->id}")
                                ->label(__('admin.actions.unanswerable'))
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn () => $question->markReviewed(EmailQuestion::ReviewStatusUnanswerable, auth()->id())),
                        ])
                            ->label(__('admin.actions.classify'))
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make("ai_classification_{$question->id}")
                            ->label(__('admin.fields.ai_classification'))
                            ->state(fn (): ?string => $question->fresh()->classification)
                            ->badge()
                            ->placeholder(__('admin.placeholders.not_classified_yet'))
                            ->color(fn (?string $state): string => $state === null ? 'gray' : EmailQuestion::classificationColor($state))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? __('admin.statuses.classification.not_classified') : EmailQuestion::classificationOptions()[$state] ?? $state),
                        TextEntry::make("ai_classification_confidence_{$question->id}")
                            ->label(__('admin.fields.ai_confidence'))
                            ->state(fn (): ?int => $question->fresh()->classification_confidence)
                            ->placeholder('—')
                            ->formatStateUsing(fn (int $state): string => "{$state}%"),
                        TextEntry::make("ai_classification_reason_{$question->id}")
                            ->label(__('admin.fields.ai_reasoning'))
                            ->state(fn (): ?string => $question->fresh()->classification_reason)
                            ->placeholder(__('admin.placeholders.no_reasoning_recorded'))
                            ->columnSpanFull(),
                        TextEntry::make("review_status_{$question->id}")
                            ->label(__('admin.fields.human_decision'))
                            ->state(fn (): string => $question->fresh()->review_status)
                            ->badge()
                            ->color(fn (string $state): string => EmailQuestion::reviewStatusColor($state))
                            ->formatStateUsing(fn (string $state): string => EmailQuestion::reviewStatusOptions()[$state] ?? $state),
                        TextEntry::make("reviewer_{$question->id}")
                            ->label(__('admin.fields.reviewed_by'))
                            ->state(fn (): ?string => $question->fresh()->reviewer?->name)
                            ->placeholder(__('admin.placeholders.not_reviewed_yet')),
                    ])
                    ->columns(2),
                Section::make(__('admin.sections.answer'))
                    ->key("answer_{$question->id}")
                    ->visible(fn (): bool => $question->fresh()->review_status === EmailQuestion::ReviewStatusValid)
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make("generateAnswer_{$question->id}")
                                ->label(__('admin.actions.regenerate_draft_answer'))
                                ->icon(Heroicon::Sparkles)
                                ->color('primary')
                                ->action(function () use ($question): void {
                                    EmailQuestionAnswerDraft::query()->updateOrCreate(
                                        ['email_question_id' => $question->id],
                                        EmailQuestionAnswerDraft::queuedAttributes(),
                                    );

                                    GenerateEmailQuestionAnswerDraft::dispatch($question->id);

                                    Notification::make()
                                        ->title(__('admin.notifications.draft_generation_queued_title'))
                                        ->success()
                                        ->send();
                                }),
                            Action::make("editAnswer_{$question->id}")
                                ->label(__('admin.actions.edit_final_answer'))
                                ->icon(Heroicon::PencilSquare)
                                ->schema([
                                    Textarea::make('final_answer')
                                        ->label(__('admin.fields.final_answer'))
                                        ->required()
                                        ->rows(8),
                                ])
                                ->fillForm(fn (): array => [
                                    'final_answer' => $question->fresh()->answerDraft?->final_answer
                                        ?? $question->fresh()->answerDraft?->generated_answer
                                        ?? '',
                                ])
                                ->action(function (array $data) use ($question): void {
                                    $question->fresh()->answerDraft?->update(['final_answer' => $data['final_answer']]);
                                    $question->fresh()->answerDraft?->syncApprovedSideEffects();
                                }),
                            Action::make("approveAnswer_{$question->id}")
                                ->label(__('admin.actions.approve'))
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusApproved,
                                    auth()->id(),
                                )),
                            Action::make("needsRevisionAnswer_{$question->id}")
                                ->label(__('admin.actions.needs_revision'))
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusNeedsRevision,
                                    auth()->id(),
                                )),
                            Action::make("rejectAnswer_{$question->id}")
                                ->label(__('admin.actions.reject'))
                                ->icon(Heroicon::XCircle)
                                ->color('danger')
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusRejected,
                                    auth()->id(),
                                )),
                        ])
                            ->label(__('admin.actions.draft_actions'))
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make("answer_status_{$question->id}")
                            ->label(__('admin.fields.status'))
                            ->state(fn (): ?string => $question->answerDraft()->value('status'))
                            ->badge()
                            ->icon(fn (?string $state): Heroicon|HtmlString|null => self::isActiveAnswerDraftStatus($state) ? self::spinningStatusIcon() : null)
                            ->placeholder(__('admin.placeholders.no_draft_generated_yet'))
                            ->color(fn (?string $state): string => $state === null ? 'gray' : EmailQuestionAnswerDraft::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? __('admin.placeholders.no_draft') : EmailQuestionAnswerDraft::statusOptions()[$state] ?? $state),
                        TextEntry::make("answer_generated_{$question->id}")
                            ->label(__('admin.fields.generated_answer'))
                            ->state(fn (): ?string => $question->answerDraft()->value('generated_answer'))
                            ->placeholder(__('admin.placeholders.no_draft_generated_yet'))
                            ->columnSpanFull(),
                        TextEntry::make("answer_final_{$question->id}")
                            ->label(__('admin.fields.final_answer'))
                            ->state(fn (): ?string => $question->answerDraft()->value('final_answer'))
                            ->placeholder(__('admin.placeholders.not_edited_yet'))
                            ->columnSpanFull(),
                        SchemaActions::make([
                            Action::make("quickEditAnswer_{$question->id}")
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
                                ->fillForm(fn (): array => [
                                    'final_answer' => $question->fresh()->answerDraft?->final_answer
                                        ?? $question->fresh()->answerDraft?->generated_answer
                                        ?? '',
                                ])
                                ->action(function (array $data) use ($question): void {
                                    $question->fresh()->answerDraft?->update(['final_answer' => $data['final_answer']]);
                                    $question->fresh()->answerDraft?->syncApprovedSideEffects();
                                }),
                            Action::make("quickApproveAnswer_{$question->id}")
                                ->label(__('admin.actions.approve'))
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->outlined()
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusApproved,
                                    auth()->id(),
                                )),
                            Action::make("quickNeedsRevisionAnswer_{$question->id}")
                                ->label(__('admin.actions.needs_revision'))
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->outlined()
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusNeedsRevision,
                                    auth()->id(),
                                )),
                            Action::make("quickRejectAnswer_{$question->id}")
                                ->label(__('admin.actions.reject'))
                                ->icon(Heroicon::XCircle)
                                ->color('danger')
                                ->outlined()
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusRejected,
                                    auth()->id(),
                                )),
                        ])
                            ->alignment(Alignment::Start)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<Component>
     */
    private static function threadDraftComponents(GmailMessage $record): array
    {
        $draft = $record->threadDraft()->first();

        if ($draft === null) {
            return [
                TextEntry::make('no_draft')
                    ->label('')
                    ->state(fn (): string => match (true) {
                        self::freshMessageNeedsReview($record) => __('admin.placeholders.not_composed_review'),
                        self::freshMessageAwaitingThreadDraft($record) => __('admin.placeholders.composing_draft'),
                        default => __('admin.placeholders.no_reply_needed'),
                    })
                    ->badge(fn (): bool => self::freshMessageAwaitingThreadDraft($record))
                    ->icon(fn (): Heroicon|HtmlString|null => self::freshMessageAwaitingThreadDraft($record) ? self::spinningStatusIcon() : null)
                    ->color(fn (): string => self::freshMessageAwaitingThreadDraft($record) ? 'info' : 'gray'),
            ];
        }

        return [
            TextEntry::make('draft_status')
                ->label(__('admin.fields.status'))
                ->state(fn (): ?string => $record->threadDraft()->value('status'))
                ->badge()
                ->color(fn (?string $state): string => $state === null ? 'gray' : EmailThreadDraft::statusColor($state))
                ->formatStateUsing(fn (?string $state): string => $state === null ? __('admin.placeholders.no_draft') : EmailThreadDraft::statusOptions()[$state] ?? $state),
            TextEntry::make('draft_composed_at')
                ->label(__('admin.fields.composed_at'))
                ->state(fn (): mixed => $record->threadDraft()->value('composed_at'))
                ->dateTime(),
            TextEntry::make('draft_subject')
                ->label(__('admin.fields.subject'))
                ->state(fn (): ?string => $record->threadDraft()->value('subject'))
                ->columnSpanFull(),
            TextEntry::make('draft_body')
                ->label(__('admin.fields.body_preview'))
                ->state(fn (): ?string => $record->threadDraft()->value('body'))
                ->html()
                ->prose()
                ->columnSpanFull(),
            TextEntry::make('draft_error')
                ->label(__('admin.fields.error'))
                ->state(fn (): ?string => $record->threadDraft()->value('last_error'))
                ->color('danger')
                ->placeholder(__('admin.placeholders.no_error'))
                ->visible(fn (): bool => $record->threadDraft()->value('status') === EmailThreadDraft::StatusFailed)
                ->columnSpanFull(),
            TextEntry::make('draft_next_step')
                ->label('')
                ->state(__('admin.placeholders.gmail_draft_ready'))
                ->color('info')
                ->visible(fn (): bool => in_array($record->threadDraft()->value('status'), [
                    EmailThreadDraft::StatusCreated,
                    EmailThreadDraft::StatusUpdated,
                ], true))
                ->columnSpanFull(),
        ];
    }

    private static function composedEmailNeedsPolling(GmailMessage $record): bool
    {
        return self::freshMessageAwaitingThreadDraft($record);
    }

    /**
     * Whether extraction genuinely hasn't run for this message yet, as
     * opposed to having run and found zero questions. Older messages
     * imported before `questions_extracted_at` existed have no timestamp
     * but do have question rows, so the presence of questions also counts
     * as evidence extraction ran.
     */
    private static function notYetExtracted(GmailMessage $record): bool
    {
        return $record->questions_extracted_at === null && $record->questions->isEmpty();
    }

    private static function freshMessageNeedsReview(GmailMessage $record): bool
    {
        return ($record->fresh(['questions.answerDraft']) ?? $record)->needsReview();
    }

    private static function freshMessageAwaitingThreadDraft(GmailMessage $record): bool
    {
        $freshRecord = $record->fresh(['questions.answerDraft', 'threadDraft']) ?? $record;

        if ($freshRecord->needsReview() || $freshRecord->threadDraft !== null) {
            return false;
        }

        return $freshRecord->questions->contains(
            fn (EmailQuestion $question): bool => $question->review_status === EmailQuestion::ReviewStatusValid
                && $question->answerDraft?->status === EmailQuestionAnswerDraft::StatusApproved,
        );
    }

    private static function isActiveAnswerDraftStatus(?string $status): bool
    {
        return in_array($status, [
            EmailQuestionAnswerDraft::StatusQueued,
            EmailQuestionAnswerDraft::StatusGenerating,
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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => self::applyDefaultTableSort($query))
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->columns([
                IconColumn::make('processed')
                    ->label('')
                    ->state(fn (GmailMessage $record): string => match (true) {
                        self::notYetExtracted($record), $record->needsReview() => 'pending',
                        $record->hasComposedDraft() => 'drafted',
                        default => 'resolved',
                    })
                    ->icon(fn (string $state): BackedEnum => match ($state) {
                        'pending' => Heroicon::Clock,
                        'drafted' => Heroicon::Envelope,
                        default => Heroicon::EnvelopeOpen,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'drafted' => 'success',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'pending' => __('admin.statuses.review.pending_review'),
                        'drafted' => __('admin.statuses.thread_draft.created'),
                        default => __('admin.placeholders.no_reply_needed'),
                    }),
                TextColumn::make('participant_name')->label(__('admin.fields.participant'))->placeholder(__('admin.placeholders.unknown'))->searchable(),
                TextColumn::make('questions_count')->label(__('admin.fields.questions'))->badge()->color('gray'),
                TextColumn::make('mailbox.email')->label(__('admin.fields.mailbox'))->searchable()->sortable(),
                TextColumn::make('from_email')->label(__('admin.fields.from'))->searchable()->sortable(),
                TextColumn::make('subject')->searchable()->limit(60),
                TextColumn::make('snippet')->limit(80),
                TextColumn::make('internal_date')->label(__('admin.fields.received'))->dateTime()->sortable(),
                TextColumn::make('imported_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('processing_status')
                    ->label(__('admin.fields.status'))
                    ->options([
                        'pending' => __('admin.statuses.review.pending_review'),
                        'drafted' => __('admin.statuses.thread_draft.created'),
                        'resolved' => __('admin.statuses.thread_draft.no_reply_needed'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'pending' => self::filterPendingMessages($query),
                            'drafted' => $query->whereHas('threadDraft'),
                            'resolved' => self::filterResolvedWithoutDraftMessages($query),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label(__('admin.actions.review'))
                    ->icon(Heroicon::Cog6Tooth)
                    ->modalCancelActionLabel(__('admin.actions.close'))
                    ->slideOver(),
            ]);
    }

    private static function filterPendingMessages(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where(function (Builder $query): void {
                    $query->whereNull('questions_extracted_at')->doesntHave('questions');
                })
                ->orWhereHas('questions', fn (Builder $query): Builder => self::applyQuestionNeedsReviewConstraint($query));
        });
    }

    private static function filterResolvedWithoutDraftMessages(Builder $query): Builder
    {
        return $query
            ->whereDoesntHave('threadDraft')
            ->where(function (Builder $query): void {
                $query->whereNotNull('questions_extracted_at')->orHas('questions');
            })
            ->whereDoesntHave('questions', fn (Builder $query): Builder => self::applyQuestionNeedsReviewConstraint($query));
    }

    private static function applyQuestionNeedsReviewConstraint(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where(function (Builder $query): void {
                    $query
                        ->where('review_status', EmailQuestion::ReviewStatusValid)
                        ->where(function (Builder $query): void {
                            $query
                                ->whereDoesntHave('answerDraft')
                                ->orWhereHas('answerDraft', function (Builder $query): void {
                                    $query->whereNotIn('status', [
                                        EmailQuestionAnswerDraft::StatusApproved,
                                        EmailQuestionAnswerDraft::StatusRejected,
                                    ]);
                                });
                        });
                })
                ->orWhereNotIn('review_status', [
                    EmailQuestion::ReviewStatusNoise,
                    EmailQuestion::ReviewStatusUnanswerable,
                    EmailQuestion::ReviewStatusValid,
                ]);
        });
    }

    private static function applyDefaultTableSort(Builder $query): Builder
    {
        return $query
            ->orderByRaw(<<<'SQL'
                case
                    when gmail_messages.questions_extracted_at is null and not exists (
                        select 1
                        from email_questions
                        where email_questions.gmail_message_id = gmail_messages.id
                    ) then 0
                    when exists (
                        select 1
                        from email_questions
                        left join email_question_answer_drafts
                            on email_question_answer_drafts.email_question_id = email_questions.id
                        where email_questions.gmail_message_id = gmail_messages.id
                            and (
                                (
                                    email_questions.review_status = ?
                                    and (
                                        email_question_answer_drafts.id is null
                                        or email_question_answer_drafts.status not in (?, ?)
                                    )
                                )
                                or email_questions.review_status not in (?, ?, ?)
                            )
                    ) then 0
                    else 1
                end
            SQL, [
                EmailQuestion::ReviewStatusValid,
                EmailQuestionAnswerDraft::StatusApproved,
                EmailQuestionAnswerDraft::StatusRejected,
                EmailQuestion::ReviewStatusNoise,
                EmailQuestion::ReviewStatusUnanswerable,
                EmailQuestion::ReviewStatusValid,
            ])
            ->orderByDesc('internal_date')
            ->orderByDesc('gmail_messages.id');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['mailbox', 'questions.answerDraft', 'threadDraft'])
            ->withCount('questions');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGmailMessages::route('/'),
        ];
    }
}
