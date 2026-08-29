<?php

namespace App\Jobs;

use App\Enums\AnswerDraftStatus;
use App\Models\EmailQuestionAnswerDraft;
use App\Services\EmailQuestions\AnswerSemanticSimilarityScorer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class ScoreAnswerSemanticSimilarity implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $draftId) {}

    public function uniqueId(): string
    {
        return (string) $this->draftId;
    }

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

    /**
     * Scores are recomputed on every approved save (including re-edits after
     * approval), so this only matters for the dashboard chart, not for any
     * downstream side effect -- updateQuietly() avoids re-triggering
     * syncApprovedSideEffects() (which would re-dispatch the Gmail thread
     * compose job for no reason).
     */
    public function handle(AnswerSemanticSimilarityScorer $scorer): void
    {
        $draft = EmailQuestionAnswerDraft::find($this->draftId);

        if ($draft === null || $draft->status !== AnswerDraftStatus::Approved) {
            return;
        }

        $draft->updateQuietly([
            'semantic_similarity_score' => $scorer->score(
                (string) $draft->generated_answer,
                (string) $draft->final_answer,
            ),
        ]);
    }
}
