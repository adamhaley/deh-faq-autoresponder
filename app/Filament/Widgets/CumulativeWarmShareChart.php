<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Filament\Widgets\ChartWidget;

class CumulativeWarmShareChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('admin.dashboard.cumulative_warm_share');
    }

    protected function getData(): array
    {
        $metrics = app(EmailAnswerPerformanceMetrics::class)->cumulativeFaqMetrics(30);

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.cumulative_warm_share_dataset'),
                    'data' => $metrics['cumulative_warm_share'],
                    'borderColor' => '#2f8f83',
                    'backgroundColor' => 'rgba(47, 143, 131, 0.12)',
                    'fill' => true,
                    'tension' => 0.2,
                    'spanGaps' => true,
                    'pointRadius' => 0,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => $metrics['labels'],
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
                    'display' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
