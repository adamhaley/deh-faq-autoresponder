<?php

namespace App\Console\Commands;

use App\Services\EmailQuestions\EmailQuestionClassificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:classify {--limit=50}')]
#[Description('Classify extracted email questions for human FAQ review')]
class ClassifyEmailQuestions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmailQuestionClassificationService $questions): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $classifiedQuestions = $questions->classifyPendingQuestions($limit);

        $this->info("Classified {$classifiedQuestions} email question(s).");

        return self::SUCCESS;
    }
}
