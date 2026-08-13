<?php

namespace App\Console\Commands;

use App\Services\EmailQuestions\EmailQuestionExtractionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:extract {--limit=100}')]
#[Description('Extract reviewable question candidates from imported Gmail messages')]
class ExtractEmailQuestions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmailQuestionExtractionService $questions): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $extractedQuestions = $questions->extractPendingMessages($limit);

        $this->info("Extracted {$extractedQuestions} email question(s).");

        return self::SUCCESS;
    }
}
