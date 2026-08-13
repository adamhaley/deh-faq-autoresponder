<?php

namespace App\Jobs;

use App\Models\EmailQuestionAnswerDraft;
use App\Services\EmailQuestions\EmailQuestionAnswerDraftGenerationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateEmailQuestionAnswerDraft implements ShouldQueue
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
    public function handle(EmailQuestionAnswerDraftGenerationService $drafts): void
    {
        $draft = $this->queuedDraft();

        $draft->update([
            'status' => EmailQuestionAnswerDraft::StatusGenerating,
            'generation_error' => null,
            'generation_started_at' => now(),
            'generation_failed_at' => null,
        ]);

        $drafts->generate($draft->emailQuestion);
    }

    public function failed(?Throwable $exception): void
    {
        $this->queuedDraft()->update([
            'status' => EmailQuestionAnswerDraft::StatusGenerationFailed,
            'generation_error' => $exception?->getMessage(),
            'generation_failed_at' => now(),
        ]);
    }

    private function queuedDraft(): EmailQuestionAnswerDraft
    {
        return EmailQuestionAnswerDraft::query()->firstOrCreate(
            ['email_question_id' => $this->emailQuestionId],
            EmailQuestionAnswerDraft::queuedAttributes(),
        )->load('emailQuestion');
    }
}
