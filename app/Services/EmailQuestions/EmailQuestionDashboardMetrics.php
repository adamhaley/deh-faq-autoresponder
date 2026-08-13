<?php

namespace App\Services\EmailQuestions;

use App\Models\EmailQuestion;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EmailQuestionDashboardMetrics
{
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
     * @return array{labels: list<string>, misalignment_rates: list<int|null>, reviewed_counts: list<int>}
     */
    public function dailyMisalignmentRates(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();
        $questionsByDay = $this->reviewedQuestionsQuery()
            ->whereBetween('reviewed_at', [$start, $end])
            ->get()
            ->groupBy(fn (EmailQuestion $question): string => $question->reviewed_at?->toDateString() ?? '');

        $labels = [];
        $misalignmentRates = [];
        $reviewedCounts = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $day = $date->toDateString();
            $questions = $questionsByDay->get($day, collect());
            $reviewedCount = $questions->count();
            $alignmentRate = $this->alignmentRate($questions);

            $labels[] = $date->format('M j');
            $reviewedCounts[] = $reviewedCount;
            $misalignmentRates[] = $alignmentRate === null ? null : 100 - $alignmentRate;
        }

        return [
            'labels' => $labels,
            'misalignment_rates' => $misalignmentRates,
            'reviewed_counts' => $reviewedCounts,
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
