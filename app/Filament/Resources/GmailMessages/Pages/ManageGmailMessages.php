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

    /**
     * Fixed channels, always known at mount -- see routes/channels.php for
     * why these can't be scoped to whichever record's modal is open. This
     * page's modal shows both nested per-question status and the
     * composed-email/thread status, so it subscribes to both channels.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:email-questions-pipeline,.status.changed' => '$refresh',
            'echo-private:email-threads-pipeline,.status.changed' => '$refresh',
        ];
    }
}
