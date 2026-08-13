<?php

namespace App\Models;

use Database\Factories\EmailQuestionFaqMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'email_question_id',
    'faq_entry_id',
    'rank',
    'similarity',
    'distance',
    'retrieval_metadata',
    'retrieved_at',
])]
class EmailQuestionFaqMatch extends Model
{
    /** @use HasFactory<EmailQuestionFaqMatchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'similarity' => 'float',
            'distance' => 'float',
            'retrieval_metadata' => 'array',
            'retrieved_at' => 'datetime',
        ];
    }

    public function emailQuestion(): BelongsTo
    {
        return $this->belongsTo(EmailQuestion::class);
    }

    public function faqEntry(): BelongsTo
    {
        return $this->belongsTo(FaqEntry::class);
    }
}
