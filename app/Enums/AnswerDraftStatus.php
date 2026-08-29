<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AnswerDraftStatus: string implements HasColor, HasLabel
{
    use TranslatesLabels;

    case Queued = 'queued';
    case Generating = 'generating';
    case GenerationFailed = 'generation_failed';
    case Draft = 'draft';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsRevision = 'needs_revision';

    public function getColor(): string
    {
        return match ($this) {
            self::Queued, self::Generating => 'info',
            self::GenerationFailed => 'danger',
            self::Draft => 'gray',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::NeedsRevision => 'warning',
        };
    }
}
