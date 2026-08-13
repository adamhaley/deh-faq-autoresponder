<?php

namespace App\Jobs;

use App\Models\EmailQuestion;
use App\Services\EmailQuestions\EmailQuestionFaqRetrievalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RetrieveEmailQuestionFaqMatches implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $emailQuestionId) {}

    /**
     * Execute the job.
     */
    public function handle(EmailQuestionFaqRetrievalService $retrieval): void
    {
        $question = EmailQuestion::query()->findOrFail($this->emailQuestionId);

        $question->update([
            'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusProcessing,
            'faq_retrieval_error' => null,
            'faq_retrieval_started_at' => now(),
            'faq_retrieval_failed_at' => null,
        ]);

        $retrieval->retrieve($question);

        $question->update([
            'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusCompleted,
            'faq_retrieval_completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        EmailQuestion::query()
            ->whereKey($this->emailQuestionId)
            ->update([
                'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusFailed,
                'faq_retrieval_error' => $exception?->getMessage(),
                'faq_retrieval_failed_at' => now(),
            ]);
    }
}
