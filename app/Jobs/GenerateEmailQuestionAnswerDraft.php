<?php

namespace App\Jobs;

use App\Events\RecordPipelineStatusChanged;
use App\Models\EmailQuestionAnswerDraft;
use App\Services\EmailQuestions\EmailQuestionAnswerDraftGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

class GenerateEmailQuestionAnswerDraft implements ShouldBeUnique, ShouldQueue
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
    public function handle(EmailQuestionAnswerDraftGenerationService $drafts): void
    {
        $draft = $this->queuedDraft();

        $draft->update([
            'status' => EmailQuestionAnswerDraft::StatusGenerating,
            'generation_error' => null,
            'generation_started_at' => now(),
            'generation_failed_at' => null,
        ]);
        $this->broadcastStatusChanged();

        $drafts->generate($draft->emailQuestion);
        $this->broadcastStatusChanged();
    }

    public function failed(?Throwable $exception): void
    {
        $this->queuedDraft()->update([
            'status' => EmailQuestionAnswerDraft::StatusGenerationFailed,
            'generation_error' => $exception?->getMessage(),
            'generation_failed_at' => now(),
        ]);
        $this->broadcastStatusChanged();
    }

    private function queuedDraft(): EmailQuestionAnswerDraft
    {
        return EmailQuestionAnswerDraft::query()->firstOrCreate(
            ['email_question_id' => $this->emailQuestionId],
            EmailQuestionAnswerDraft::queuedAttributes(),
        )->load('emailQuestion');
    }

    private function broadcastStatusChanged(): void
    {
        RecordPipelineStatusChanged::dispatch('email-questions-pipeline');
    }
}
