<?php

namespace App\Console\Commands;

use App\Jobs\RetrieveEmailQuestionFaqMatches as RetrieveEmailQuestionFaqMatchesJob;
use App\Models\EmailQuestion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:retrieve-faq-matches {--limit=50}')]
#[Description('Queue FAQ match retrieval for reviewed valid email questions')]
class RetrieveEmailQuestionFaqMatches extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $queuedQuestions = 0;

        EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusValid)
            ->whereNotNull('reviewed_at')
            ->whereDoesntHave('faqMatches')
            ->whereIn('faq_retrieval_status', [
                EmailQuestion::FaqRetrievalStatusNotStarted,
                EmailQuestion::FaqRetrievalStatusFailed,
            ])
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (EmailQuestion $question) use (&$queuedQuestions): void {
                $question->update([
                    'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusQueued,
                    'faq_retrieval_error' => null,
                    'faq_retrieval_failed_at' => null,
                ]);

                RetrieveEmailQuestionFaqMatchesJob::dispatch($question->id);
                $queuedQuestions++;
            });

        $this->info("Queued FAQ match retrieval for {$queuedQuestions} email question(s).");

        return self::SUCCESS;
    }
}
