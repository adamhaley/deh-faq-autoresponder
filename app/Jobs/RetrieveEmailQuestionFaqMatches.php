<?php

namespace App\Jobs;

use App\Events\RecordPipelineStatusChanged;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use App\Services\EmailQuestions\EmailQuestionFaqRetrievalService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class RetrieveEmailQuestionFaqMatches implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 120;

    public int $uniqueFor = 600;

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
        ];
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function uniqueId(): string
    {
        return (string) $this->emailQuestionId;
    }

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
        $this->broadcastStatusChanged();

        $retrieval->retrieve($question);

        $question->update([
            'faq_retrieval_status' => EmailQuestion::FaqRetrievalStatusCompleted,
            'faq_retrieval_completed_at' => now(),
        ]);
        $this->broadcastStatusChanged();

        $this->dispatchAnswerGeneration($question->fresh());
    }

    /**
     * Chains straight into answer generation once matches are in, so the
     * whole pipeline runs automatically after a reviewer marks a question
     * Valid. Skipped for questions whose answer already reached a terminal
     * (approved/rejected) human decision, so this never clobbers a review.
     */
    private function dispatchAnswerGeneration(EmailQuestion $question): void
    {
        if ($question->review_status !== EmailQuestion::ReviewStatusValid) {
            return;
        }

        if ($question->faqMatches()->doesntExist()) {
            return;
        }

        if (in_array($question->answerDraft?->status, [
            EmailQuestionAnswerDraft::StatusApproved,
            EmailQuestionAnswerDraft::StatusRejected,
        ], true)) {
            return;
        }

        EmailQuestionAnswerDraft::query()->updateOrCreate(
            ['email_question_id' => $question->id],
            EmailQuestionAnswerDraft::queuedAttributes(),
        );

        GenerateEmailQuestionAnswerDraft::dispatch($question->id);
        $this->broadcastStatusChanged();
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
        $this->broadcastStatusChanged();
    }

    private function broadcastStatusChanged(): void
    {
        RecordPipelineStatusChanged::dispatch('email-questions-pipeline');
    }
}
