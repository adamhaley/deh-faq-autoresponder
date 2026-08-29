<?php

namespace App\Enums;

use App\Enums\Concerns\TranslatesLabels;
use Filament\Support\Contracts\HasLabel;

enum GmailMailboxSyncStatus: string implements HasLabel
{
    use TranslatesLabels;

    case Connected = 'connected';
    case Failed = 'failed';
    case ResyncRequired = 'resync_required';
}
