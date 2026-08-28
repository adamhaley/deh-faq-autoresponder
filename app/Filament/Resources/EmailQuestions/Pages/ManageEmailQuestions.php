<?php

namespace App\Filament\Resources\EmailQuestions\Pages;

use App\Filament\Resources\EmailQuestions\EmailQuestionResource;
use App\Models\EmailQuestion;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailQuestions extends ManageRecords
{
    protected static string $resource = EmailQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $record = $this->getMountedTableActionRecord();

        if (! $record instanceof EmailQuestion) {
            return [];
        }

        return [
            "echo-private:email-questions.{$record->id},.status.changed" => '$refresh',
        ];
    }
}
