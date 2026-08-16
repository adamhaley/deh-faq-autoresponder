<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailAnswerPerformanceMetrics;
use Filament\Widgets\ChartWidget;

class AnswerSimilarityChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('admin.dashboard.answer_similarity');
    }

    protected function getData(): array
    {
        $metrics = app(EmailAnswerPerformanceMetrics::class)->dailySimilarityScores(30);

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.answer_similarity_dataset'),
                    'data' => $metrics['similarity_scores'],
                    'borderColor' => '#2f8f83',
                    'backgroundColor' => 'rgba(47, 143, 131, 0.16)',
                    'fill' => true,
                    'tension' => 0.35,
                    'spanGaps' => true,
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
