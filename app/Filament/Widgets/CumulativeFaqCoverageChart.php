<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Filament\Widgets\ChartWidget;

class CumulativeFaqCoverageChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('admin.dashboard.cumulative_faq_coverage');
    }

    protected function getData(): array
    {
        $metrics = app(EmailAnswerPerformanceMetrics::class)->cumulativeFaqMetrics(30);

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.cumulative_faq_coverage_dataset'),
                    'data' => $metrics['cumulative_faq_coverage'],
                    'borderColor' => '#8f1024',
                    'backgroundColor' => 'rgba(143, 16, 36, 0.12)',
                    'fill' => true,
                    'tension' => 0.2,
                    'stepped' => false,
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
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
