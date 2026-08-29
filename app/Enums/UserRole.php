<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    use TranslatesLabels;

    case Admin = 'admin';
    case Reviewer = 'reviewer';
    case Viewer = 'viewer';

    public function canReviewResponses(): bool
    {
        return $this !== self::Viewer;
    }
}
