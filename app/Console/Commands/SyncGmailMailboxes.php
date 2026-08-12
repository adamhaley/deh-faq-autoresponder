<?php

namespace App\Console\Commands;

use App\Services\Gmail\GmailMailboxSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gmail:sync-mailboxes')]
#[Description('Sync active Gmail mailboxes into the local incoming message table')]
class SyncGmailMailboxes extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(GmailMailboxSyncService $mailboxes): int
    {
        $importedMessages = $mailboxes->syncActiveMailboxes();

        $this->info("Imported {$importedMessages} Gmail message(s).");

        return self::SUCCESS;
    }
}
