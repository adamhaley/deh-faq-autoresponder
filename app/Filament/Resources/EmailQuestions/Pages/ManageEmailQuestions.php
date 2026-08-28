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

    /**
     * Fixed channel, always known at mount -- see routes/channels.php for
     * why this can't be scoped to whichever record's modal is open.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:email-questions-pipeline,.status.changed' => '$refresh',
        ];
    }
}
