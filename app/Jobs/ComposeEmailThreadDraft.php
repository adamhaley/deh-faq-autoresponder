<?php

namespace App\Jobs;

use App\Services\EmailQuestions\EmailThreadDraftComposerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ComposeEmailThreadDraft implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $threadId) {}

    public function handle(EmailThreadDraftComposerService $composer): void
    {
        $composer->composeForThread($this->threadId);
    }
}
