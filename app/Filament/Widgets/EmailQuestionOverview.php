<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailQuestionDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class EmailQuestionOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $metrics = app(EmailQuestionDashboardMetrics::class);
        $since = Carbon::now()->subDays(7);
        $reviewed = $metrics->reviewedSince($since);
        $agreementRate = $metrics->agreementRateSince($since);
        $disagreements = $metrics->disagreementCountSince($since);

        return [
            Stat::make('Pending review', number_format($metrics->pendingReviewCount()))
                ->description('Questions waiting on human classification')
                ->color('warning'),
            Stat::make('Reviewed last 7 days', number_format($reviewed))
                ->description('Human-reviewed question classifications')
                ->color('info'),
            Stat::make('AI/Human agreement', $agreementRate === null ? 'N/A' : "{$agreementRate}%")
                ->description($reviewed === 0 ? 'No reviewed data yet' : 'Last 7 days')
                ->color($agreementRate === null || $agreementRate < 70 ? 'warning' : 'success'),
            Stat::make('Disagreements last 7 days', number_format($disagreements))
                ->description('Human review differed from AI classification')
                ->color($disagreements > 0 ? 'danger' : 'success'),
        ];
    }
}
