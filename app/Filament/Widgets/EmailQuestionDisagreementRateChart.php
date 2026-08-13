<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailQuestionDashboardMetrics;
use Filament\Widgets\ChartWidget;

class EmailQuestionDisagreementRateChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'AI/Human Disagreement Rate';

    protected function getData(): array
    {
        $metrics = app(EmailQuestionDashboardMetrics::class)->dailyDisagreementRates(30);

        return [
            'datasets' => [
                [
                    'label' => 'Disagreement rate',
                    'data' => $metrics['disagreement_rates'],
                    'borderColor' => '#d16068',
                    'backgroundColor' => 'rgba(209, 96, 104, 0.16)',
                    'fill' => true,
                    'tension' => 0.35,
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
