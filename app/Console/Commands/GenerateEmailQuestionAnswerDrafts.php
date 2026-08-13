<?php

namespace App\Console\Commands;

use App\Services\EmailQuestions\EmailQuestionAnswerDraftGenerationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:generate-answer-drafts {--limit=50}')]
#[Description('Generate answer drafts for reviewed valid email questions with FAQ matches')]
class GenerateEmailQuestionAnswerDrafts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmailQuestionAnswerDraftGenerationService $questions): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $generatedDrafts = $questions->generateForReadyQuestions($limit);

        $this->info("Generated {$generatedDrafts} answer draft(s).");

        return self::SUCCESS;
    }
}
