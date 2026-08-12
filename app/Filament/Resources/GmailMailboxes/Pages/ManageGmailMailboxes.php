<?php

namespace App\Filament\Resources\GmailMailboxes\Pages;

use App\Filament\Resources\GmailMailboxes\GmailMailboxResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGmailMailboxes extends ManageRecords
{
    protected static string $resource = GmailMailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
