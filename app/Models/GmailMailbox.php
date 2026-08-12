<?php

namespace App\Models;

use Database\Factories\GmailMailboxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'email',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'scopes',
    'label_ids',
    'import_query',
    'last_history_id',
    'last_sync_at',
    'is_active',
    'sync_status',
    'last_error',
    'connected_by_user_id',
])]
#[Hidden(['access_token', 'refresh_token'])]
class GmailMailbox extends Model
{
    /** @use HasFactory<GmailMailboxFactory> */
    use HasFactory;

    public const DefaultLabelIds = ['INBOX'];

    public const SyncStatusConnected = 'connected';

    public const SyncStatusFailed = 'failed';

    public const SyncStatusResyncRequired = 'resync_required';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sync_status' => self::SyncStatusConnected,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'scopes' => 'array',
            'label_ids' => 'array',
            'last_sync_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public function syncLabelIds(): array
    {
        if (! is_array($this->label_ids) || $this->label_ids === []) {
            return self::DefaultLabelIds;
        }

        return array_values(array_filter($this->label_ids, is_string(...)));
    }

    public function connectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(GmailMessage::class);
    }
}
