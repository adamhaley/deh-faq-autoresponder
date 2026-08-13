<?php

namespace App\Models;

use Database\Factories\FaqEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['question', 'answer', 'embedding'])]
class FaqEntry extends Model
{
    /** @use HasFactory<FaqEntryFactory> */
    use HasFactory;

    use HasUuids;

    public function approvedResponse(): HasOne
    {
        return $this->hasOne(FaqApprovedResponse::class);
    }

    public function emailQuestionMatches(): HasMany
    {
        return $this->hasMany(EmailQuestionFaqMatch::class);
    }
}
