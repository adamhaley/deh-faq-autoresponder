<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Filament\Widgets\ChartWidget;

class WeeklyRepetitionChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('admin.dashboard.weekly_repetition');
    }

    protected function getData(): array
    {
        $weekly = app(EmailAnswerPerformanceMetrics::class)->weeklyRepetitionCounts(8);

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.weekly_repetition_cold_dataset'),
                    'data' => $weekly['cold_counts'],
                    'backgroundColor' => '#94a3b8',
                ],
                [
                    'label' => __('admin.dashboard.weekly_repetition_warm_dataset'),
                    'data' => $weekly['warm_counts'],
                    'backgroundColor' => '#2f8f83',
                ],
            ],
            'labels' => $weekly['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
