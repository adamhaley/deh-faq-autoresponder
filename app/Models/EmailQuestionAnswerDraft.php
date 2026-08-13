<?php

namespace App\Models;

use Database\Factories\EmailQuestionAnswerDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'email_question_id',
    'reviewed_by_user_id',
    'generated_answer',
    'final_answer',
    'status',
    'generation_reason',
    'generation_metadata',
    'generated_at',
    'reviewed_at',
])]
class EmailQuestionAnswerDraft extends Model
{
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
            'generated_at' => 'datetime',
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
            self::StatusDraft => 'Draft',
            self::StatusApproved => 'Approved',
            self::StatusRejected => 'Rejected',
            self::StatusNeedsRevision => 'Needs revision',
        ];
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            self::StatusApproved => 'success',
            self::StatusRejected => 'danger',
            self::StatusNeedsRevision => 'warning',
            default => 'gray',
        };
    }

    public function markReviewed(string $status, ?int $reviewerId, ?string $finalAnswer = null): bool
    {
        return $this->update([
            'status' => $status,
            'reviewed_by_user_id' => $reviewerId,
            'final_answer' => $finalAnswer ?? $this->final_answer ?? $this->generated_answer,
            'reviewed_at' => now(),
        ]);
    }
}
