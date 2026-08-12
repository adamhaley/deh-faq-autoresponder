<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['faq_entry_id', 'approved_response', 'match_similarity'])]
class FaqApprovedResponse extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return [
            'match_similarity' => 'float',
        ];
    }

    public function faqEntry(): BelongsTo
    {
        return $this->belongsTo(FaqEntry::class);
    }
}
