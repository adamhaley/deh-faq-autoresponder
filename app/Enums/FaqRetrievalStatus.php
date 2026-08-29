<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FaqRetrievalStatus: string implements HasColor, HasLabel
{
    use TranslatesLabels;

    case NotStarted = 'not_started';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::Queued, self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }
}
