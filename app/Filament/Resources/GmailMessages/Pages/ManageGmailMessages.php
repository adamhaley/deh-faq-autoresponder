<?php

namespace App\Filament\Resources\GmailMessages\Pages;

use App\Filament\Resources\GmailMessages\GmailMessageResource;
use Filament\Resources\Pages\ManageRecords;

class ManageGmailMessages extends ManageRecords
{
    protected static string $resource = GmailMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
