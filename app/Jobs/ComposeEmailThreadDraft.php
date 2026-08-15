<?php

namespace App\Jobs;

use App\Services\EmailQuestions\EmailThreadDraftComposerService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class ComposeEmailThreadDraft implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public string $threadId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 30, 120];
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new RateLimited('openai'))->releaseAfter(30),
            (new RateLimited('gmail'))->releaseAfter(30),
        ];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function uniqueId(): string
    {
        return $this->threadId;
    }

    public function handle(EmailThreadDraftComposerService $composer): void
    {
        $composer->composeForThread($this->threadId);
    }
}
