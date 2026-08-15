<?php

namespace App\Models;

use Database\Factories\GmailMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'gmail_mailbox_id',
    'gmail_message_id',
    'thread_id',
    'history_id',
    'subject',
    'from_email',
    'from_name',
    'participant_name',
    'reply_to_email',
    'to_recipients',
    'cc_recipients',
    'snippet',
    'text_body',
    'html_body',
    'label_ids',
    'internal_date',
    'payload',
    'imported_at',
])]
#[Appends(['mailbox_email'])]
class GmailMessage extends Model
{
    /** @use HasFactory<GmailMessageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'label_ids' => 'array',
            'internal_date' => 'datetime',
            'payload' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<?string, never>
     */
    protected function mailboxEmail(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->mailbox?->email,
        );
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(GmailMailbox::class, 'gmail_mailbox_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(EmailQuestion::class);
    }

    /**
     * Threads never span more than one message in this app (the mailbox
     * only receives automated one-off webinar-question emails, never
     * back-and-forth replies), so this is effectively 1:1 -- but the
     * composed draft is genuinely keyed on thread_id, not message id.
     */
    public function threadDraft(): HasOne
    {
        return $this->hasOne(EmailThreadDraft::class, 'thread_id', 'thread_id');
    }

    /**
     * Whether a reviewer still has something to do here: any extracted
     * question that hasn't been assigned a review decision yet, or that
     * was marked Valid but whose answer hasn't reached a terminal
     * (approved/rejected) state. A message with no extracted questions, or
     * where every question is resolved, needs no attention.
     */
    public function needsReview(): bool
    {
        return $this->questions->contains(function (EmailQuestion $question): bool {
            if ($question->review_status === EmailQuestion::ReviewStatusValid) {
                return ! in_array($question->answerDraft?->status, [
                    EmailQuestionAnswerDraft::StatusApproved,
                    EmailQuestionAnswerDraft::StatusRejected,
                ], true);
            }

            return ! in_array($question->review_status, [
                EmailQuestion::ReviewStatusNoise,
                EmailQuestion::ReviewStatusUnanswerable,
            ], true);
        });
    }
}
