<?php

namespace App\Console\Commands;

use App\Services\EmailQuestions\EmailQuestionFaqRetrievalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:retrieve-faq-matches {--limit=50}')]
#[Description('Retrieve FAQ matches for reviewed valid email questions')]
class RetrieveEmailQuestionFaqMatches extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmailQuestionFaqRetrievalService $questions): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $retrievedQuestions = $questions->retrieveForReviewedValidQuestions($limit);

        $this->info("Retrieved FAQ matches for {$retrievedQuestions} email question(s).");

        return self::SUCCESS;
    }
}
