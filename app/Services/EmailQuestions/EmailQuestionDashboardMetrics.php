<?php

namespace App\Services\EmailQuestions;

use App\Models\EmailQuestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmailQuestionDashboardMetrics
{
    public const RecentReviews = 300;

    public const TrendWindow = 30;

    public function pendingReviewCount(): int
    {
        return EmailQuestion::query()
            ->where('review_status', EmailQuestion::ReviewStatusPendingReview)
            ->count();
    }

    public function reviewedSince(Carbon $since): int
    {
        return $this->reviewedQuestionsQuery()
            ->where('reviewed_at', '>=', $since)
            ->count();
    }

    public function alignmentRateSince(Carbon $since): ?int
    {
        $questions = $this->reviewedQuestionsQuery()
            ->where('reviewed_at', '>=', $since)
            ->get();

        return $this->alignmentRate($questions);
    }

    public function misalignmentCountSince(Carbon $since): int
    {
        return $this->reviewedQuestionsQuery()
            ->where('reviewed_at', '>=', $since)
            ->get()
            ->reject(fn (EmailQuestion $question): bool => $this->alignsWithHumanReview($question))
            ->count();
    }

    /**
     * Rolling misalignment rate over the last TrendWindow reviewed
     * questions, for each of the most recent RecentReviews, oldest first.
     *
     * By review order, not calendar day: reviewers work in bursts, and a
     * rolling calendar window scrolls everything out of view during a quiet
     * stretch. Rate rather than per-question points, since a single
     * question is only ever aligned or not. Points before a full window
     * exists are dropped once there is enough data, since a rate over the
     * first one or two reviews swings between 0 and 100.
     *
     * @return array{labels: list<string>, misalignment_rates: list<int>}
     */
    public function recentMisalignmentRates(): array
    {
        $questions = $this->reviewedQuestionsQuery()
            ->latest('reviewed_at')
            ->latest('id')
            ->limit(self::RecentReviews)
            ->get()
            ->reverse()
            ->values();

        $labels = [];
        $misalignmentRates = [];

        foreach ($questions as $index => $question) {
            $offset = max(0, $index - self::TrendWindow + 1);

            $labels[] = $question->reviewed_at?->clone()->timezone(config('app.display_timezone'))->format('M j') ?? '';
            $misalignmentRates[] = 100 - (int) $this->alignmentRate($questions->slice($offset, $index - $offset + 1));
        }

        $warmUp = count($misalignmentRates) > self::TrendWindow ? self::TrendWindow - 1 : 0;

        return [
            'labels' => array_slice($labels, $warmUp),
            'misalignment_rates' => array_slice($misalignmentRates, $warmUp),
        ];
    }

    /**
     * @return Builder<EmailQuestion>
     */
    public function recentMisalignmentsQuery(): Builder
    {
        return $this->misalignmentsQuery()
            ->with('message:id,from_email,subject')
            ->latest('reviewed_at');
    }

    /**
     * @return Builder<EmailQuestion>
     */
    private function misalignmentsQuery(): Builder
    {
        return $this->addMisalignmentConstraint($this->reviewedQuestionsQuery());
    }

    /**
     * @return Builder<EmailQuestion>
     */
    private function reviewedQuestionsQuery(): Builder
    {
        return EmailQuestion::query()
            ->select([
                'id',
                'gmail_message_id',
                'question_text',
                'classification',
                'review_status',
                'reviewed_at',
            ])
            ->whereNotNull('reviewed_at')
            ->where('review_status', '!=', EmailQuestion::ReviewStatusPendingReview);
    }

    /**
     * @param  Builder<EmailQuestion>  $query
     * @return Builder<EmailQuestion>
     */
    private function addMisalignmentConstraint(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereNull('classification')
                ->orWhere(function (Builder $query): void {
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
        });
    }

    /**
     * @param  Collection<int, EmailQuestion>  $questions
     */
    private function alignmentRate(Collection $questions): ?int
    {
        $reviewedCount = $questions->count();

        if ($reviewedCount === 0) {
            return null;
        }

        $alignments = $questions
            ->filter(fn (EmailQuestion $question): bool => $this->alignsWithHumanReview($question))
            ->count();

        return (int) round(($alignments / $reviewedCount) * 100);
    }

    private function alignsWithHumanReview(EmailQuestion $question): bool
    {
        return $this->humanStatusForClassification($question->classification) === $question->review_status;
    }

    private function humanStatusForClassification(?string $classification): ?string
    {
        return match ($classification) {
            EmailQuestion::ClassificationValidFaqQuestion => EmailQuestion::ReviewStatusValid,
            EmailQuestion::ClassificationNoise => EmailQuestion::ReviewStatusNoise,
            EmailQuestion::ClassificationUnanswerable => EmailQuestion::ReviewStatusUnanswerable,
            EmailQuestion::ClassificationNeedsHuman => EmailQuestion::ReviewStatusNeedsHuman,
            default => null,
        };
    }
}
