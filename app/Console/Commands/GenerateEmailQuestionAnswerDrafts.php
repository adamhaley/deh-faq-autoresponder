<?php

namespace App\Console\Commands;

use App\Enums\AnswerDraftStatus;
use App\Jobs\GenerateEmailQuestionAnswerDraft;
use App\Models\EmailQuestion;
use App\Models\EmailQuestionAnswerDraft;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:generate-answer-drafts {--limit=50}')]
#[Description('Queue answer draft generation for reviewed valid email questions with FAQ matches')]
class GenerateEmailQuestionAnswerDrafts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $queuedDrafts = 0;

        EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusValid)
            ->whereHas('faqMatches')
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('answerDraft')
                    ->orWhereHas('answerDraft', fn ($drafts) => $drafts->where(
                        'status',
                        AnswerDraftStatus::GenerationFailed,
                    ));
            })
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (EmailQuestion $question) use (&$queuedDrafts): void {
                EmailQuestionAnswerDraft::query()->updateOrCreate(
                    ['email_question_id' => $question->id],
                    EmailQuestionAnswerDraft::queuedAttributes(),
                );

                GenerateEmailQuestionAnswerDraft::dispatch($question->id);
                $queuedDrafts++;
            });

        $this->info("Queued answer draft generation for {$queuedDrafts} email question(s).");

        return self::SUCCESS;
    }
}
