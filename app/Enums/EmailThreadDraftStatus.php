<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmailThreadDraftStatus: string implements HasColor, HasLabel
{
    use TranslatesLabels;

    case Created = 'created';
    case Updated = 'updated';
    case Failed = 'failed';

    public function getColor(): string
    {
        return match ($this) {
            self::Created, self::Updated => 'success',
            self::Failed => 'danger',
        };
    }
}
