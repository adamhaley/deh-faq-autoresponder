<?php

namespace App\Models;

use Database\Factories\GmailMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'gmail_mailbox_id',
    'gmail_message_id',
    'thread_id',
    'history_id',
    'subject',
    'from_email',
    'from_name',
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

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(GmailMailbox::class, 'gmail_mailbox_id');
    }
}
