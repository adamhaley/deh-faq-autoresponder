<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailQuestionDashboardMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class EmailQuestionOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $metrics = app(EmailQuestionDashboardMetrics::class);
        $since = Carbon::now()->subDays(7);
        $reviewed = $metrics->reviewedSince($since);
        $alignmentRate = $metrics->alignmentRateSince($since);
        $misalignments = $metrics->misalignmentCountSince($since);

        return [
            Stat::make(__('admin.dashboard.pending_review'), number_format($metrics->pendingReviewCount()))
                ->description(__('admin.dashboard.pending_review_description'))
                ->color('warning'),
            Stat::make(__('admin.dashboard.reviewed_last_7_days'), number_format($reviewed))
                ->description(__('admin.dashboard.reviewed_last_7_days_description'))
                ->color('info'),
            Stat::make(__('admin.dashboard.ai_human_alignment'), $alignmentRate === null ? 'N/A' : "{$alignmentRate}%")
                ->description($reviewed === 0 ? __('admin.dashboard.no_reviewed_data_yet') : __('admin.dashboard.last_7_days'))
                ->color($alignmentRate === null || $alignmentRate < 70 ? 'warning' : 'success'),
            Stat::make(__('admin.dashboard.misalignments_last_7_days'), number_format($misalignments))
                ->description(__('admin.dashboard.misalignments_last_7_days_description'))
                ->color($misalignments > 0 ? 'danger' : 'success'),
        ];
    }
}
