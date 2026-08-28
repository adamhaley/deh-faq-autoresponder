<?php

namespace App\Filament\Resources\GmailMessages\Pages;

use App\Filament\Resources\GmailMessages\GmailMessageResource;
use App\Models\GmailMessage;
use Filament\Resources\Pages\ManageRecords;

class ManageGmailMessages extends ManageRecords
{
    protected static string $resource = GmailMessageResource::class;

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

        if (! $record instanceof GmailMessage) {
            return [];
        }

        $listeners = [
            "echo-private:email-threads.{$record->thread_id},.status.changed" => '$refresh',
        ];

        foreach ($record->questions()->pluck('id') as $questionId) {
            $listeners["echo-private:email-questions.{$questionId},.status.changed"] = '$refresh';
        }

        return $listeners;
    }
}
