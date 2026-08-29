<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmailQuestionClassification: string implements HasColor, HasLabel
{
    use TranslatesLabels;

    case ValidFaqQuestion = 'valid_faq_question';
    case Noise = 'noise';
    case Unanswerable = 'unanswerable';
    case NeedsHuman = 'needs_human';

    public function getColor(): string
    {
        return match ($this) {
            self::ValidFaqQuestion => 'success',
            self::Noise => 'gray',
            self::Unanswerable => 'warning',
            self::NeedsHuman => 'info',
        };
    }
}
