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
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class GmailMessageResource extends Resource
{
    protected static ?string $model = GmailMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Webinar Responses';

    protected static ?string $modelLabel = 'webinar response';

    protected static ?string $pluralModelLabel = 'webinar responses';

    protected static ?int $navigationSort = -2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mailbox_email')
                    ->label('Mailbox')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('from_email')
                    ->label('From')
                    ->disabled(),
                TextInput::make('subject')
                    ->disabled(),
                TextInput::make('internal_date')
                    ->disabled(),
                Textarea::make('snippet')
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('text_body')
                    ->label('Text body')
                    ->readOnly()
                    ->columnSpanFull(),
                Textarea::make('html_body')
                    ->label('HTML body')
                    ->readOnly()
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message')
                    ->schema([
                        TextEntry::make('participant_name')->label('Participant')->placeholder('Unknown'),
                        TextEntry::make('reply_to_email')->label('Email address'),
                        TextEntry::make('internal_date')->label('Received')->dateTime(),
                        TextEntry::make('snippet')
                            ->label('Preview')
                            ->placeholder('No preview available.')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Questions')
                    ->schema(fn (GmailMessage $record): array => self::questionComponents($record))
                    ->poll(fn (GmailMessage $record): ?string => $record->questions()->get()->contains(
                        fn (EmailQuestion $question): bool => $question->hasActiveAsyncPipeline(),
                    ) ? '3s' : null)
                    ->columnSpanFull(),
                Section::make('Composed Email')
                    ->afterHeader([
                        Action::make('editTemplate')
                            ->label('Edit template')
                            ->icon(Heroicon::PencilSquare)
                            ->color('gray')
                            ->link()
                            ->url(fn (): ?string => EmailTemplateResource::getUrl())
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
                    ->state('No questions have been extracted from this message yet.'),
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
                            : 'No draft yet',
                    ))
                    ->badge()
                    ->color(fn (): string => EmailQuestion::reviewStatusColor($question->fresh()->review_status)),
                TextEntry::make("question_{$question->id}_text")
                    ->label('Question')
                    ->state($question->question_text)
                    ->columnSpanFull(),
                Section::make('Review')
                    ->key("review_{$question->id}")
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make("markValid_{$question->id}")
                                ->label('Valid question')
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn () => $question->markReviewed(EmailQuestion::ReviewStatusValid, auth()->id())),
                            Action::make("markNoise_{$question->id}")
                                ->label('Noise')
                                ->icon(Heroicon::XCircle)
                                ->color('gray')
                                ->action(fn () => $question->markReviewed(EmailQuestion::ReviewStatusNoise, auth()->id())),
                            Action::make("markUnanswerable_{$question->id}")
                                ->label('Unanswerable')
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn () => $question->markReviewed(EmailQuestion::ReviewStatusUnanswerable, auth()->id())),
                        ])
                            ->label('Classify')
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make("ai_classification_{$question->id}")
                            ->label('AI classification')
                            ->state(fn (): ?string => $question->fresh()->classification)
                            ->badge()
                            ->placeholder('Not classified yet')
                            ->color(fn (?string $state): string => $state === null ? 'gray' : EmailQuestion::classificationColor($state))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? 'Not classified' : EmailQuestion::classificationOptions()[$state] ?? $state),
                        TextEntry::make("ai_classification_confidence_{$question->id}")
                            ->label('AI confidence')
                            ->state(fn (): ?int => $question->fresh()->classification_confidence)
                            ->placeholder('—')
                            ->formatStateUsing(fn (int $state): string => "{$state}%"),
                        TextEntry::make("ai_classification_reason_{$question->id}")
                            ->label('AI reasoning')
                            ->state(fn (): ?string => $question->fresh()->classification_reason)
                            ->placeholder('No reasoning recorded.')
                            ->columnSpanFull(),
                        TextEntry::make("review_status_{$question->id}")
                            ->label('Human decision')
                            ->state(fn (): string => $question->fresh()->review_status)
                            ->badge()
                            ->color(fn (string $state): string => EmailQuestion::reviewStatusColor($state))
                            ->formatStateUsing(fn (string $state): string => EmailQuestion::reviewStatusOptions()[$state] ?? $state),
                        TextEntry::make("reviewer_{$question->id}")
                            ->label('Reviewed by')
                            ->state(fn (): ?string => $question->fresh()->reviewer?->name)
                            ->placeholder('Not reviewed yet'),
                    ])
                    ->columns(2),
                Section::make('Answer')
                    ->key("answer_{$question->id}")
                    ->visible(fn (): bool => $question->fresh()->review_status === EmailQuestion::ReviewStatusValid)
                    ->afterHeader([
                        ActionGroup::make([
                            Action::make("generateAnswer_{$question->id}")
                                ->label('Regenerate draft answer')
                                ->icon(Heroicon::Sparkles)
                                ->color('primary')
                                ->action(function () use ($question): void {
                                    EmailQuestionAnswerDraft::query()->updateOrCreate(
                                        ['email_question_id' => $question->id],
                                        EmailQuestionAnswerDraft::queuedAttributes(),
                                    );

                                    GenerateEmailQuestionAnswerDraft::dispatch($question->id);

                                    Notification::make()
                                        ->title('Draft generation queued')
                                        ->success()
                                        ->send();
                                }),
                            Action::make("editAnswer_{$question->id}")
                                ->label('Edit final answer')
                                ->icon(Heroicon::PencilSquare)
                                ->schema([
                                    Textarea::make('final_answer')
                                        ->label('Final answer')
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
                                ->label('Approve')
                                ->icon(Heroicon::CheckCircle)
                                ->color('success')
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusApproved,
                                    auth()->id(),
                                )),
                            Action::make("needsRevisionAnswer_{$question->id}")
                                ->label('Needs revision')
                                ->icon(Heroicon::ExclamationTriangle)
                                ->color('warning')
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusNeedsRevision,
                                    auth()->id(),
                                )),
                            Action::make("rejectAnswer_{$question->id}")
                                ->label('Reject')
                                ->icon(Heroicon::XCircle)
                                ->color('danger')
                                ->action(fn () => $question->fresh()->answerDraft?->markReviewed(
                                    EmailQuestionAnswerDraft::StatusRejected,
                                    auth()->id(),
                                )),
                        ])
                            ->label('Draft actions')
                            ->icon(Heroicon::ChevronDown)
                            ->button(),
                    ])
                    ->schema([
                        TextEntry::make("answer_status_{$question->id}")
                            ->label('Status')
                            ->state(fn (): ?string => $question->answerDraft()->value('status'))
                            ->badge()
                            ->placeholder('No draft generated yet')
                            ->color(fn (?string $state): string => $state === null ? 'gray' : EmailQuestionAnswerDraft::statusColor($state))
                            ->formatStateUsing(fn (?string $state): string => $state === null ? 'No draft' : EmailQuestionAnswerDraft::statusOptions()[$state] ?? $state),
                        TextEntry::make("answer_generated_{$question->id}")
                            ->label('Generated answer')
                            ->state(fn (): ?string => $question->answerDraft()->value('generated_answer'))
                            ->placeholder('No draft generated yet')
                            ->columnSpanFull(),
                        TextEntry::make("answer_final_{$question->id}")
                            ->label('Final answer')
                            ->state(fn (): ?string => $question->answerDraft()->value('final_answer'))
                            ->placeholder('Not edited yet')
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
                    ->state(fn (): string => self::freshMessageNeedsReview($record)
                        ? 'Not composed yet - approve or resolve every question above first.'
                        : 'No reply needed - no relevant questions to answer.'),
            ];
        }

        return [
            TextEntry::make('draft_status')
                ->label('Status')
                ->state(fn (): ?string => $record->threadDraft()->value('status'))
                ->badge()
                ->color(fn (?string $state): string => $state === null ? 'gray' : EmailThreadDraft::statusColor($state))
                ->formatStateUsing(fn (?string $state): string => $state === null ? 'No draft' : EmailThreadDraft::statusOptions()[$state] ?? $state),
            TextEntry::make('draft_composed_at')
                ->label('Composed at')
                ->state(fn (): mixed => $record->threadDraft()->value('composed_at'))
                ->dateTime(),
            TextEntry::make('draft_subject')
                ->label('Subject')
                ->state(fn (): ?string => $record->threadDraft()->value('subject'))
                ->columnSpanFull(),
            TextEntry::make('draft_body')
                ->label('Body preview')
                ->state(fn (): ?string => $record->threadDraft()->value('body'))
                ->html()
                ->prose()
                ->columnSpanFull(),
            TextEntry::make('draft_error')
                ->label('Error')
                ->state(fn (): ?string => $record->threadDraft()->value('last_error'))
                ->color('danger')
                ->placeholder('No error')
                ->visible(fn (): bool => $record->threadDraft()->value('status') === EmailThreadDraft::StatusFailed)
                ->columnSpanFull(),
        ];
    }

    private static function composedEmailNeedsPolling(GmailMessage $record): bool
    {
        return (! self::freshMessageNeedsReview($record)) && $record->threadDraft()->doesntExist();
    }

    private static function freshMessageNeedsReview(GmailMessage $record): bool
    {
        return ($record->fresh(['questions.answerDraft']) ?? $record)->needsReview();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('internal_date', 'desc')
            ->columns([
                IconColumn::make('processed')
                    ->label('')
                    ->state(fn (GmailMessage $record): string => match (true) {
                        $record->questions->isEmpty(), $record->needsReview() => 'pending',
                        $record->hasComposedDraft() => 'drafted',
                        default => 'resolved',
                    })
                    ->icon(fn (string $state): BackedEnum => $state === 'pending' ? Heroicon::Clock : Heroicon::CheckCircle)
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'drafted' => 'success',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => match ($state) {
                        'pending' => 'Needs review',
                        'drafted' => 'Draft composed',
                        default => 'Resolved, no draft needed',
                    }),
                TextColumn::make('participant_name')->label('Participant')->placeholder('Unknown')->searchable(),
                TextColumn::make('mailbox.email')->label('Mailbox')->searchable()->sortable(),
                TextColumn::make('from_email')->label('From')->searchable()->sortable(),
                TextColumn::make('subject')->searchable()->limit(60),
                TextColumn::make('snippet')->limit(80),
                TextColumn::make('questions_count')->label('Questions')->badge(),
                TextColumn::make('internal_date')->label('Received')->dateTime()->sortable(),
                TextColumn::make('imported_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalCancelActionLabel('Close')
                    ->slideOver(),
            ]);
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
