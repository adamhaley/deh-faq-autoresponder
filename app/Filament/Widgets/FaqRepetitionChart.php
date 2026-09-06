<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Filament\Widgets\ChartWidget;

class FaqRepetitionChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 1;

    public function getHeading(): string
    {
        return __('admin.dashboard.faq_repetition_distribution');
    }

    protected function getData(): array
    {
        $distribution = app(EmailAnswerPerformanceMetrics::class)->faqRepetitionDistribution();

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.faq_repetition_dataset'),
                    'data' => $distribution['counts'],
                    'backgroundColor' => ['#94a3b8', '#38bdf8', '#2f8f83'],
                ],
            ],
            'labels' => $distribution['labels'],
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
        return 'bar';
    }
}
