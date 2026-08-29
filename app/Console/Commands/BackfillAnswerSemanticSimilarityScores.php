<?php

namespace App\Console\Commands;

use App\Enums\AnswerDraftStatus;
use App\Models\EmailQuestionAnswerDraft;
use App\Services\EmailQuestions\AnswerSemanticSimilarityScorer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('email-questions:backfill-semantic-similarity {--limit=50}')]
#[Description('Score approved answer drafts that predate the semantic-similarity feature')]
class BackfillAnswerSemanticSimilarityScores extends Command
{
    /**
     * A one-off admin backfill over a modest, already-deduplicated batch has
     * no concurrent-dispatch risk to guard against and shouldn't compete
     * with live traffic for the shared 'openai' rate limit -- so this runs
     * synchronously instead of going through ScoreAnswerSemanticSimilarity's
     * ShouldBeUnique/RateLimited queue path, which is designed for the
     * single-record, event-triggered approval flow, not batch backfills.
     */
    public function handle(AnswerSemanticSimilarityScorer $scorer): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $drafts = EmailQuestionAnswerDraft::query()
            ->where('status', AnswerDraftStatus::Approved)
            ->whereNull('semantic_similarity_score')
            ->whereNotNull('generated_answer')
            ->whereNotNull('final_answer')
            ->where('generated_answer', '!=', '')
            ->where('final_answer', '!=', '')
            ->oldest('id')
            ->limit($limit)
            ->get(['id', 'generated_answer', 'final_answer']);

        $scored = 0;
        $failed = 0;

        $this->withProgressBar($drafts, function (EmailQuestionAnswerDraft $draft) use ($scorer, &$scored, &$failed): void {
            try {
                $draft->updateQuietly([
                    'semantic_similarity_score' => $scorer->score($draft->generated_answer, $draft->final_answer),
                ]);
                $scored++;
            } catch (Throwable $e) {
                $failed++;
                $this->newLine();
                $this->error("Draft {$draft->id}: {$e->getMessage()}");
            }
        });
        $this->newLine();

        $this->info("Scored {$scored} approved answer draft(s).".($failed > 0 ? " {$failed} failed." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
