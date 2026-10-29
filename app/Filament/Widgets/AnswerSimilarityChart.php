<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Filament\Widgets\ChartWidget;

class AnswerSimilarityChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('admin.dashboard.answer_similarity');
    }

    protected function getData(): array
    {
        $metrics = app(EmailAnswerPerformanceMetrics::class)->recentWordingSimilarity();

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.answer_similarity_reviews'),
                    'data' => $metrics['scores'],
                    'showLine' => false,
                    'borderColor' => 'rgba(47, 143, 131, 0.45)',
                    'backgroundColor' => 'rgba(47, 143, 131, 0.45)',
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                ],
                [
                    'label' => __('admin.dashboard.answer_similarity_dataset', ['count' => EmailAnswerPerformanceMetrics::TrendWindow]),
                    'data' => $metrics['trend'],
                    'borderColor' => '#2f8f83',
                    'backgroundColor' => 'rgba(47, 143, 131, 0.16)',
                    'fill' => true,
                    'cubicInterpolationMode' => 'monotone',
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
                    'display' => true,
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'maxTicksLimit' => 10,
                    ],
                ],
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
