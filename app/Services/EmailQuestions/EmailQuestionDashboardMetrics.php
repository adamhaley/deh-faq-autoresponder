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

    public function agreementRateSince(Carbon $since): ?int
    {
        $questions = $this->reviewedQuestionsQuery()
            ->where('reviewed_at', '>=', $since)
            ->get();

        return $this->agreementRate($questions);
    }

    public function disagreementCountSince(Carbon $since): int
    {
        return $this->reviewedQuestionsQuery()
            ->where('reviewed_at', '>=', $since)
            ->get()
            ->reject(fn (EmailQuestion $question): bool => $this->agreesWithHumanReview($question))
            ->count();
    }

    /**
     * @return array{labels: list<string>, disagreement_rates: list<int|null>, reviewed_counts: list<int>}
     */
    public function dailyDisagreementRates(int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $end = now()->endOfDay();
        $questionsByDay = $this->reviewedQuestionsQuery()
            ->whereBetween('reviewed_at', [$start, $end])
            ->get()
            ->groupBy(fn (EmailQuestion $question): string => $question->reviewed_at?->toDateString() ?? '');

        $labels = [];
        $disagreementRates = [];
        $reviewedCounts = [];

        foreach (CarbonPeriod::create($start, '1 day', $end) as $date) {
            $day = $date->toDateString();
            $questions = $questionsByDay->get($day, collect());
            $reviewedCount = $questions->count();
            $agreementRate = $this->agreementRate($questions);

            $labels[] = $date->format('M j');
            $reviewedCounts[] = $reviewedCount;
            $disagreementRates[] = $agreementRate === null ? null : 100 - $agreementRate;
        }

        return [
            'labels' => $labels,
            'disagreement_rates' => $disagreementRates,
            'reviewed_counts' => $reviewedCounts,
        ];
    }

    /**
     * @return Builder<EmailQuestion>
     */
    public function recentDisagreementsQuery(): Builder
    {
        return $this->disagreementsQuery()
            ->with('message:id,from_email,subject')
            ->latest('reviewed_at');
    }

    /**
     * @return Builder<EmailQuestion>
     */
    private function disagreementsQuery(): Builder
    {
        return $this->addDisagreementConstraint($this->reviewedQuestionsQuery());
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
    private function addDisagreementConstraint(Builder $query): Builder
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
    private function agreementRate(Collection $questions): ?int
    {
        $reviewedCount = $questions->count();

        if ($reviewedCount === 0) {
            return null;
        }

        $agreements = $questions
            ->filter(fn (EmailQuestion $question): bool => $this->agreesWithHumanReview($question))
            ->count();

        return (int) round(($agreements / $reviewedCount) * 100);
    }

    private function agreesWithHumanReview(EmailQuestion $question): bool
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
