<?php

namespace App\Services\EmailQuestions;

use App\Ai\Agents\EmailQuestionClassifier;
use App\Enums\EmailQuestionClassification;
use App\Enums\EmailQuestionReviewStatus;
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
            'review_status' => EmailQuestionReviewStatus::PendingReview,
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
                            ->where('classification', EmailQuestionClassification::ValidFaqQuestion)
                            ->where('review_status', '!=', EmailQuestionReviewStatus::Valid);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestionClassification::Noise)
                            ->where('review_status', '!=', EmailQuestionReviewStatus::Noise);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestionClassification::Unanswerable)
                            ->where('review_status', '!=', EmailQuestionReviewStatus::Unanswerable);
                    })
                    ->orWhere(function (Builder $query): void {
                        $query
                            ->where('classification', EmailQuestionClassification::NeedsHuman)
                            ->where('review_status', '!=', EmailQuestionReviewStatus::NeedsHuman);
                    });
            })
            ->latest('reviewed_at')
            ->limit(3)
            ->get();

        $examples = $examples->merge($misalignments);

        foreach ([
            EmailQuestionReviewStatus::Valid,
            EmailQuestionReviewStatus::Noise,
            EmailQuestionReviewStatus::Unanswerable,
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
            ->where('review_status', '!=', EmailQuestionReviewStatus::PendingReview)
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
                    $example->review_status?->getLabel(),
                    $example->message?->subject ?? '',
                    $example->message?->from_email ?? '',
                );
            })
            ->implode("\n\n");
    }

    private function classificationLabel(?EmailQuestionClassification $classification): string
    {
        return $classification?->getLabel() ?? 'Unclassified';
    }

    private function classification(mixed $classification): EmailQuestionClassification
    {
        if (! is_string($classification)) {
            return EmailQuestionClassification::NeedsHuman;
        }

        return EmailQuestionClassification::tryFrom($classification) ?? EmailQuestionClassification::NeedsHuman;
    }

    private function confidence(mixed $confidence): int
    {
        if (! is_numeric($confidence)) {
            return 0;
        }

        return max(0, min(100, (int) $confidence));
    }
}
