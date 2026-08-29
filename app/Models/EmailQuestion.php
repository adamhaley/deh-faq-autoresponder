<?php

namespace App\Models;

use App\Enums\AnswerDraftStatus;
use App\Enums\EmailQuestionClassification;
use App\Enums\EmailQuestionReviewStatus;
use App\Enums\FaqRetrievalStatus;
use App\Jobs\RetrieveEmailQuestionFaqMatches;
use Database\Factories\EmailQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'gmail_message_id',
    'reviewed_by_user_id',
    'question_order',
    'question_text',
    'normalized_question',
    'question_hash',
    'classification',
    'classification_confidence',
    'classification_reason',
    'review_status',
    'parser_version',
    'extraction_metadata',
    'classification_metadata',
    'classified_at',
    'reviewed_at',
    'faq_retrieval_status',
    'faq_retrieval_error',
    'faq_retrieval_started_at',
    'faq_retrieval_completed_at',
    'faq_retrieval_failed_at',
])]
class EmailQuestion extends Model
{
    /** @use HasFactory<EmailQuestionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'question_order' => 1,
        'review_status' => EmailQuestionReviewStatus::PendingReview->value,
        'faq_retrieval_status' => FaqRetrievalStatus::NotStarted->value,
        'parser_version' => 'n8n-chat-v1',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'classification_confidence' => 'integer',
            'extraction_metadata' => 'array',
            'classification_metadata' => 'array',
            'classified_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'faq_retrieval_started_at' => 'datetime',
            'faq_retrieval_completed_at' => 'datetime',
            'faq_retrieval_failed_at' => 'datetime',
            'classification' => EmailQuestionClassification::class,
            'review_status' => EmailQuestionReviewStatus::class,
            'faq_retrieval_status' => FaqRetrievalStatus::class,
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(GmailMessage::class, 'gmail_message_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function faqMatches(): HasMany
    {
        return $this->hasMany(EmailQuestionFaqMatch::class)
            ->orderBy('rank');
    }

    public function answerDraft(): HasOne
    {
        return $this->hasOne(EmailQuestionAnswerDraft::class);
    }

    public function hasActiveFaqRetrieval(): bool
    {
        return in_array($this->newQuery()->whereKey($this)->value('faq_retrieval_status'), [
            FaqRetrievalStatus::Queued,
            FaqRetrievalStatus::Processing,
        ], true);
    }

    public function hasActiveAnswerDraftGeneration(): bool
    {
        return in_array($this->answerDraft()->value('status'), [
            AnswerDraftStatus::Queued,
            AnswerDraftStatus::Generating,
        ], true);
    }

    public function hasActiveAsyncPipeline(): bool
    {
        return $this->hasActiveFaqRetrieval()
            || $this->hasActiveAnswerDraftGeneration();
    }

    public function scopeWithActiveAsyncPipeline(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereIn('faq_retrieval_status', [
                    FaqRetrievalStatus::Queued,
                    FaqRetrievalStatus::Processing,
                ])
                ->orWhereHas('answerDraft', function (Builder $query): void {
                    $query->whereIn('status', [
                        AnswerDraftStatus::Queued,
                        AnswerDraftStatus::Generating,
                    ]);
                });
        });
    }

    /**
     * Marking a question Valid immediately queues automatic FAQ retrieval
     * (which, on completion, chains into automatic answer generation) so
     * reviewers never have to trigger the pipeline by hand.
     */
    public function markReviewed(EmailQuestionReviewStatus $status, ?int $reviewerId): bool
    {
        $updated = $this->update([
            'review_status' => $status,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);

        if ($updated && $status === EmailQuestionReviewStatus::Valid) {
            $this->update([
                'faq_retrieval_status' => FaqRetrievalStatus::Queued,
                'faq_retrieval_error' => null,
                'faq_retrieval_failed_at' => null,
            ]);

            RetrieveEmailQuestionFaqMatches::dispatch($this->id);
        }

        return $updated;
    }
}
