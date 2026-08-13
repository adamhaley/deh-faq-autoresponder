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
        $alignmentRate = $metrics->alignmentRateSince($since);
        $misalignments = $metrics->misalignmentCountSince($since);

        return [
            Stat::make('Pending review', number_format($metrics->pendingReviewCount()))
                ->description('Questions waiting on human classification')
                ->color('warning'),
            Stat::make('Reviewed last 7 days', number_format($reviewed))
                ->description('Human-reviewed question classifications')
                ->color('info'),
            Stat::make('AI/Human alignment', $alignmentRate === null ? 'N/A' : "{$alignmentRate}%")
                ->description($reviewed === 0 ? 'No reviewed data yet' : 'Last 7 days')
                ->color($alignmentRate === null || $alignmentRate < 70 ? 'warning' : 'success'),
            Stat::make('Misalignments last 7 days', number_format($misalignments))
                ->description('Human review was not aligned with AI classification')
                ->color($misalignments > 0 ? 'danger' : 'success'),
        ];
    }
}
