<?php

namespace App\Console\Commands;

use App\Jobs\ComposeEmailThreadDraft;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Models\GmailMessage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Composing a thread's Gmail draft is normally triggered immediately when an
 * answer is approved (see EmailQuestionAnswerDraft::syncApprovedSideEffects).
 * This command is a safety net for threads that somehow never got a draft
 * despite having an approved answer -- a dropped queue job, a worker outage,
 * etc. -- so an approval can never go silently uncomposed.
 */
#[Signature('email-questions:compose-pending-drafts {--limit=50}')]
#[Description('Queue thread draft composition for approved answers missing a Gmail draft')]
class ComposePendingEmailThreadDrafts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $queuedThreads = 0;

        GmailMessage::query()
            ->whereHas('questions', fn ($query) => $query
                ->where('review_status', EmailQuestion::ReviewStatusValid)
                ->whereHas('answerDraft', fn ($draftQuery) => $draftQuery->where(
                    'status',
                    EmailQuestionAnswerDraft::StatusApproved,
                )))
            ->whereDoesntHave('threadDraft')
            ->pluck('thread_id')
            ->unique()
            ->take($limit)
            ->each(function (string $threadId) use (&$queuedThreads): void {
                ComposeEmailThreadDraft::dispatch($threadId);
                $queuedThreads++;
            });

        $this->info("Queued draft composition for {$queuedThreads} thread(s).");

        return self::SUCCESS;
    }
}
