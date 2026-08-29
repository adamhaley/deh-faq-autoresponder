<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmailQuestionReviewStatus: string implements HasColor, HasLabel
{
    use TranslatesLabels;

    case PendingReview = 'pending_review';
    case Valid = 'valid';
    case Noise = 'noise';
    case Unanswerable = 'unanswerable';
    case NeedsHuman = 'needs_human';

    public function getColor(): string
    {
        return match ($this) {
            self::PendingReview => 'gray',
            self::Valid => 'success',
            self::Noise => 'gray',
            self::Unanswerable => 'warning',
            self::NeedsHuman => 'info',
        };
    }

    /**
     * The terminal decisions a human reviewer can actively choose.
     *
     * @return list<self>
     */
    public static function reviewerDecisionCases(): array
    {
        return [self::Valid, self::Noise, self::Unanswerable];
    }

    /**
     * Reviewer decisions plus the not-yet-reviewed state, for filtering.
     *
     * @return list<self>
     */
    public static function reviewerFilterCases(): array
    {
        return [self::PendingReview, ...self::reviewerDecisionCases()];
    }
}
