<?php

namespace App\Models;

use Database\Factories\EmailQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
    public const ClassificationValidFaqQuestion = 'valid_faq_question';

    public const ClassificationNoise = 'noise';

    public const ClassificationUnanswerable = 'unanswerable';

    public const ClassificationNeedsHuman = 'needs_human';

    public const ReviewStatusPendingReview = 'pending_review';

    public const ReviewStatusValid = 'valid';

    public const ReviewStatusNoise = 'noise';

    public const ReviewStatusUnanswerable = 'unanswerable';

    public const ReviewStatusNeedsHuman = 'needs_human';

    public const FaqRetrievalStatusNotStarted = 'not_started';

    public const FaqRetrievalStatusQueued = 'queued';

    public const FaqRetrievalStatusProcessing = 'processing';

    public const FaqRetrievalStatusCompleted = 'completed';

    public const FaqRetrievalStatusFailed = 'failed';

    /** @use HasFactory<EmailQuestionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'question_order' => 1,
        'review_status' => self::ReviewStatusPendingReview,
        'faq_retrieval_status' => self::FaqRetrievalStatusNotStarted,
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

    /**
     * @return array<string, string>
     */
    public static function classificationOptions(): array
    {
        return [
            self::ClassificationValidFaqQuestion => 'Valid question',
            self::ClassificationNoise => 'Noise',
            self::ClassificationUnanswerable => 'Unanswerable',
            self::ClassificationNeedsHuman => 'Needs human',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function reviewStatusOptions(): array
    {
        return [
            self::ReviewStatusPendingReview => 'Pending review',
            self::ReviewStatusValid => 'Valid question',
            self::ReviewStatusNoise => 'Noise',
            self::ReviewStatusUnanswerable => 'Unanswerable',
            self::ReviewStatusNeedsHuman => 'Needs human',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function humanReviewDecisionOptions(): array
    {
        return [
            self::ReviewStatusValid => 'Valid question',
            self::ReviewStatusNoise => 'Noise',
            self::ReviewStatusUnanswerable => 'Unanswerable',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function humanReviewFilterOptions(): array
    {
        return [
            self::ReviewStatusPendingReview => 'Pending review',
            ...self::humanReviewDecisionOptions(),
        ];
    }

    public static function classificationColor(?string $classification): string
    {
        return match ($classification) {
            self::ClassificationValidFaqQuestion => 'success',
            self::ClassificationNoise => 'gray',
            self::ClassificationUnanswerable => 'warning',
            self::ClassificationNeedsHuman => 'info',
            default => 'gray',
        };
    }

    public static function reviewStatusColor(string $status): string
    {
        return match ($status) {
            self::ReviewStatusValid => 'success',
            self::ReviewStatusNoise => 'gray',
            self::ReviewStatusUnanswerable => 'warning',
            self::ReviewStatusNeedsHuman => 'info',
            default => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function faqRetrievalStatusOptions(): array
    {
        return [
            self::FaqRetrievalStatusNotStarted => 'Not started',
            self::FaqRetrievalStatusQueued => 'Queued',
            self::FaqRetrievalStatusProcessing => 'Processing',
            self::FaqRetrievalStatusCompleted => 'Completed',
            self::FaqRetrievalStatusFailed => 'Failed',
        ];
    }

    public static function faqRetrievalStatusColor(string $status): string
    {
        return match ($status) {
            self::FaqRetrievalStatusQueued, self::FaqRetrievalStatusProcessing => 'info',
            self::FaqRetrievalStatusCompleted => 'success',
            self::FaqRetrievalStatusFailed => 'danger',
            default => 'gray',
        };
    }

    public function markReviewed(string $status, ?int $reviewerId): bool
    {
        return $this->update([
            'review_status' => $status,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }
}
