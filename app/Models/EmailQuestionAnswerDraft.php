<?php

namespace App\Models;

use Database\Factories\EmailQuestionAnswerDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

#[Fillable([
    'email_question_id',
    'reviewed_by_user_id',
    'generated_answer',
    'final_answer',
    'status',
    'generation_reason',
    'generation_metadata',
    'generation_error',
    'generation_started_at',
    'generated_at',
    'generation_failed_at',
    'reviewed_at',
])]
class EmailQuestionAnswerDraft extends Model
{
    public const PendingGeneratedAnswer = '[Queued for generation]';

    public const StatusQueued = 'queued';

    public const StatusGenerating = 'generating';

    public const StatusGenerationFailed = 'generation_failed';

    public const StatusDraft = 'draft';

    public const StatusApproved = 'approved';

    public const StatusRejected = 'rejected';

    public const StatusNeedsRevision = 'needs_revision';

    /** @use HasFactory<EmailQuestionAnswerDraftFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generation_metadata' => 'array',
            'generation_started_at' => 'datetime',
            'generated_at' => 'datetime',
            'generation_failed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function emailQuestion(): BelongsTo
    {
        return $this->belongsTo(EmailQuestion::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::StatusQueued => 'Queued',
            self::StatusGenerating => 'Generating',
            self::StatusGenerationFailed => 'Generation failed',
            self::StatusDraft => 'Draft',
            self::StatusApproved => 'Approved',
            self::StatusRejected => 'Rejected',
            self::StatusNeedsRevision => 'Needs revision',
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::StatusQueued, self::StatusGenerating => 'info',
            self::StatusGenerationFailed => 'danger',
            self::StatusApproved => 'success',
            self::StatusRejected => 'danger',
            self::StatusNeedsRevision => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function queuedAttributes(): array
    {
        return [
            'generated_answer' => self::PendingGeneratedAnswer,
            'final_answer' => null,
            'status' => self::StatusQueued,
            'generation_reason' => null,
            'generation_metadata' => null,
            'generation_error' => null,
            'generation_started_at' => null,
            'generated_at' => null,
            'generation_failed_at' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function markReviewed(string $status, ?int $reviewerId, ?string $finalAnswer = null): bool
    {
        $finalAnswer ??= $this->final_answer ?? $this->generated_answer;

        $updated = $this->update([
            'status' => $status,
            'reviewed_by_user_id' => $reviewerId,
            'final_answer' => $finalAnswer,
            'reviewed_at' => now(),
        ]);

        if ($updated && $status === self::StatusApproved) {
            $this->applyFinalAnswerAsFaqOverride($finalAnswer);
        }

        return $updated;
    }

    /**
     * Feed the operator-approved final answer back into future retrieval
     * prompts by saving it as the override for the best-matched FAQ. This is
     * the only write path for FAQ overrides — deliberately automatic and
     * scoped to the single best match, so the loop stays simple to reason
     * about instead of fanning an email-specific answer out across every
     * retrieved FAQ.
     */
    private function applyFinalAnswerAsFaqOverride(?string $finalAnswer): void
    {
        if ($finalAnswer === null || $finalAnswer === '') {
            return;
        }

        $bestMatch = $this->emailQuestion
            ?->faqMatches()
            ->orderBy('rank')
            ->first();

        if ($bestMatch === null) {
            return;
        }

        FaqApprovedResponse::query()->updateOrCreate(
            ['faq_entry_id' => $bestMatch->faq_entry_id],
            [
                'approved_response' => $finalAnswer,
                'match_similarity' => $bestMatch->similarity,
            ],
        );

        Log::info('Applied approved final answer as FAQ override.', [
            'email_question_answer_draft_id' => $this->id,
            'faq_entry_id' => $bestMatch->faq_entry_id,
        ]);
    }
}
