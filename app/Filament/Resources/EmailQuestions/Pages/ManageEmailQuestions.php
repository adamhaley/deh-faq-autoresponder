<?php

namespace App\Filament\Resources\EmailQuestions\Pages;

use App\Filament\Resources\EmailQuestions\EmailQuestionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailQuestions extends ManageRecords
{
    protected static string $resource = EmailQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
