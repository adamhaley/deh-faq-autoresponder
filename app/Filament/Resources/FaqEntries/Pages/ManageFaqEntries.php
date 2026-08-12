<?php

namespace App\Filament\Resources\FaqEntries\Pages;

use App\Filament\Resources\FaqEntries\FaqEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFaqEntries extends ManageRecords
{
    protected static string $resource = FaqEntryResource::class;
}
