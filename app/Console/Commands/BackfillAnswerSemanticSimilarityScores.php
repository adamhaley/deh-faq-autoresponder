<?php

namespace App\Console\Commands;

use App\Jobs\ScoreAnswerSemanticSimilarity;
use App\Models\EmailQuestionAnswerDraft;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:backfill-semantic-similarity {--limit=50}')]
#[Description('Queue semantic-similarity scoring for approved answer drafts that predate the feature')]
class BackfillAnswerSemanticSimilarityScores extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $queuedDrafts = 0;

        EmailQuestionAnswerDraft::query()
            ->where('status', EmailQuestionAnswerDraft::StatusApproved)
            ->whereNull('semantic_similarity_score')
            ->whereNotNull('generated_answer')
            ->whereNotNull('final_answer')
            ->where('generated_answer', '!=', '')
            ->where('final_answer', '!=', '')
            ->oldest('id')
            ->limit($limit)
            ->pluck('id')
            ->each(function (int $draftId) use (&$queuedDrafts): void {
                ScoreAnswerSemanticSimilarity::dispatch($draftId);
                $queuedDrafts++;
            });

        $this->info("Queued semantic-similarity scoring for {$queuedDrafts} approved answer draft(s).");

        return self::SUCCESS;
    }
}
