<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use Filament\Resources\Pages\ManageRecords;

/**
 * There is deliberately only one email template today, so this page has no
 * create/delete actions -- edit-only, behaving as a singleton.
 */
class ManageEmailTemplates extends ManageRecords
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
