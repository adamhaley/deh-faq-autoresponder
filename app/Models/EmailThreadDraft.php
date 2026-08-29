<?php

namespace App\Models;

use App\Enums\EmailThreadDraftStatus;
use Database\Factories\EmailThreadDraftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gmail_mailbox_id',
    'thread_id',
    'gmail_draft_id',
    'subject',
    'body',
    'status',
    'last_error',
    'composed_at',
])]
class EmailThreadDraft extends Model
{
    /** @use HasFactory<EmailThreadDraftFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'composed_at' => 'datetime',
            'status' => EmailThreadDraftStatus::class,
        ];
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(GmailMailbox::class, 'gmail_mailbox_id');
    }
}
