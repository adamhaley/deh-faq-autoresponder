<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['question', 'answer', 'embedding'])]
class FaqEntry extends Model
{
    use HasUuids;

    public function approvedResponse(): HasOne
    {
        return $this->hasOne(FaqApprovedResponse::class);
    }
}
