<?php

namespace App\Services\EmailQuestions;

use App\Ai\Agents\EmailQuestionClassifier;
use App\Models\EmailQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EmailQuestionClassificationService
{
    private const MaxTrainingExamples = 8;

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
        $trainingExamples = $this->reviewedExamplesFor($question);
        $response = (new EmailQuestionClassifier)->prompt($this->prompt($question, $trainingExamples));

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
                'training_example_ids' => $trainingExamples->pluck('id')->values()->all(),
            ],
            'classified_at' => now(),
        ]);

        return $question->refresh();
    }

    /**
     * @param  Collection<int, EmailQuestion>  $trainingExamples
     */
    private function prompt(EmailQuestion $question, Collection $trainingExamples): string
    {
        $message = $question->message;
        $examples = $this->formatTrainingExamples($trainingExamples);

        return sprintf(
            <<<'PROMPT'
Classify this extracted email question for FAQ processing.

Use the human-reviewed examples as guidance. The human classification is the correct classification for each example. Do not copy an example blindly; use it to calibrate what this client considers a valid question, noise, or unanswerable.

Human-reviewed examples:
%s

Extracted question:
%s

Email context:
Subject: %s
From: %s
Snippet: %s
PROMPT,
            $examples,
            $question->question_text,
            $message?->subject ?? '',
            $message?->from_email ?? '',
            Str::limit($message?->snippet ?? '', 500),
        );
    }

    /**
     * @return Collection<int, EmailQuestion>
     */
    private function reviewedExamplesFor(EmailQuestion $question): Collection
    {
        $examples = collect();

        $misalignments = $this->reviewedExampleQuery($question)
            ->whereNotNull('classification')
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestion::ClassificationValidFaqQuestion)
                            ->where('review_status', '!=', EmailQuestion::ReviewStatusValid);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestion::ClassificationNoise)
                            ->where('review_status', '!=', EmailQuestion::ReviewStatusNoise);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestion::ClassificationUnanswerable)
                            ->where('review_status', '!=', EmailQuestion::ReviewStatusUnanswerable);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestion::ClassificationNeedsHuman)
                            ->where('review_status', '!=', EmailQuestion::ReviewStatusNeedsHuman);
                    });
            })
            ->latest('reviewed_at')
            ->limit(3)
            ->get();

        $examples = $examples->merge($misalignments);

        foreach ([
            EmailQuestion::ReviewStatusValid,
            EmailQuestion::ReviewStatusNoise,
            EmailQuestion::ReviewStatusUnanswerable,
        ] as $status) {
            $examples = $examples->merge(
                $this->reviewedExampleQuery($question)
                    ->where('review_status', $status)
                    ->latest('reviewed_at')
                    ->limit(2)
                    ->get(),
            );
        }

        return $examples
            ->unique('id')
            ->take(self::MaxTrainingExamples)
            ->values();
    }

    /**
     * @return Builder<EmailQuestion>
     */
    private function reviewedExampleQuery(EmailQuestion $question): Builder
    {
        return EmailQuestion::query()
            ->select([
                'id',
                'gmail_message_id',
                'question_text',
                'classification',
                'review_status',
                'reviewed_at',
                'reviewed_by_user_id',
            ])
            ->whereKeyNot($question->id)
            ->whereNotNull('reviewed_at')
            ->whereNotNull('reviewed_by_user_id')
            ->whereNotNull('question_text')
            ->where('review_status', '!=', EmailQuestion::ReviewStatusPendingReview)
            ->with('message:id,subject,from_email');
    }

    /**
     * @param  Collection<int, EmailQuestion>  $trainingExamples
     */
    private function formatTrainingExamples(Collection $trainingExamples): string
    {
        if ($trainingExamples->isEmpty()) {
            return 'No human-reviewed examples are available yet.';
        }

        return $trainingExamples
            ->map(function (EmailQuestion $example, int $index): string {
                return sprintf(
                    <<<'EXAMPLE'
Example %d:
Question: %s
Original AI classification: %s
Correct human classification: %s
Subject: %s
From: %s
EXAMPLE,
                    $index + 1,
                    $example->question_text,
                    $this->classificationLabel($example->classification),
                    EmailQuestion::reviewStatusOptions()[$example->review_status] ?? $example->review_status,
                    $example->message?->subject ?? '',
                    $example->message?->from_email ?? '',
                );
            })
            ->implode("\n\n");
    }

    private function classificationLabel(?string $classification): string
    {
        if ($classification === null || $classification === '') {
            return 'Unclassified';
        }

        return EmailQuestion::classificationOptions()[$classification] ?? $classification;
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
