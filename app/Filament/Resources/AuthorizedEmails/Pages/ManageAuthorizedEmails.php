<?php

namespace App\Filament\Resources\AuthorizedEmails\Pages;

use App\Filament\Resources\AuthorizedEmails\AuthorizedEmailResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAuthorizedEmails extends ManageRecords
{
    protected static string $resource = AuthorizedEmailResource::class;
}
