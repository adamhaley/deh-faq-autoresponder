<?php

namespace App\Services\EmailQuestions;

use App\Ai\Agents\EmailQuestionClassifier;
use App\Models\EmailQuestion;
use Illuminate\Support\Str;

class EmailQuestionClassificationService
{
    public function classifyPendingQuestions(int $limit = 50): int
    {
        $classifiedQuestions = 0;

        EmailQuestion::query()
            ->whereNull('classified_at')
            ->with('message:id,subject,from_email,snippet,text_body')
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (EmailQuestion $question) use (&$classifiedQuestions): void {
                $this->classify($question);
                $classifiedQuestions++;
            });

        return $classifiedQuestions;
    }

    public function classify(EmailQuestion $question): EmailQuestion
    {
        $response = (new EmailQuestionClassifier)->prompt($this->prompt($question));

        $classification = $this->classification($response['classification'] ?? null);
        $confidence = $this->confidence($response['confidence'] ?? null);
        $reason = is_string($response['reason'] ?? null) ? $response['reason'] : 'Classifier did not provide a usable reason.';
        $normalizedQuestion = is_string($response['normalized_question'] ?? null) ? trim($response['normalized_question']) : null;

        $question->update([
            'classification' => $classification,
            'classification_confidence' => $confidence,
            'classification_reason' => $reason,
            'normalized_question' => $normalizedQuestion === '' ? null : $normalizedQuestion,
            'review_status' => EmailQuestion::ReviewStatusPendingReview,
            'classification_metadata' => [
                'classifier' => EmailQuestionClassifier::class,
            ],
            'classified_at' => now(),
        ]);

        return $question->refresh();
    }

    private function prompt(EmailQuestion $question): string
    {
        $message = $question->message;

        return sprintf(
            <<<'PROMPT'
Classify this extracted email question for FAQ processing.

Extracted question:
%s

Email context:
Subject: %s
From: %s
Snippet: %s
PROMPT,
            $question->question_text,
            $message?->subject ?? '',
            $message?->from_email ?? '',
            Str::limit($message?->snippet ?? '', 500),
        );
    }

    private function classification(mixed $classification): string
    {
        if (! is_string($classification)) {
            return EmailQuestion::ClassificationNeedsHuman;
        }

        $allowed = [
            EmailQuestion::ClassificationValidFaqQuestion,
            EmailQuestion::ClassificationNoise,
            EmailQuestion::ClassificationUnanswerable,
            EmailQuestion::ClassificationNeedsHuman,
        ];

        return in_array($classification, $allowed, true)
            ? $classification
            : EmailQuestion::ClassificationNeedsHuman;
    }

    private function confidence(mixed $confidence): int
    {
        if (! is_numeric($confidence)) {
            return 0;
        }

        return max(0, min(100, (int) $confidence));
    }
}
