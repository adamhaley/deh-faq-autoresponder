<?php

namespace App\Console\Commands;

use App\Models\GmailMessage;
use App\Services\EmailQuestions\EmailQuestionExtractionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('email-questions:extract {--limit=100} {--message=* : Gmail message ID(s) to force re-extract, discarding their existing questions}')]
#[Description('Extract reviewable question candidates from imported Gmail messages')]
class ExtractEmailQuestions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(EmailQuestionExtractionService $questions): int
    {
        $messageIds = array_map('intval', $this->option('message'));

        if ($messageIds !== []) {
            return $this->reextract($questions, $messageIds);
        }

        $limit = max(1, (int) $this->option('limit'));
        $extractedQuestions = $questions->extractPendingMessages($limit);

        $this->info("Extracted {$extractedQuestions} email question(s).");

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $messageIds
     */
    private function reextract(EmailQuestionExtractionService $questions, array $messageIds): int
    {
        $extractedQuestions = 0;

        foreach (GmailMessage::query()->findMany($messageIds) as $message) {
            $extractedQuestions += $questions->reextractMessage($message)->count();
        }

        $this->info("Re-extracted {$extractedQuestions} email question(s) across ".count($messageIds).' message(s).');

        return self::SUCCESS;
    }
}
