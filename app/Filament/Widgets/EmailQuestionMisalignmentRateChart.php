<?php

namespace App\Filament\Widgets;

use App\Services\EmailQuestions\EmailQuestionDashboardMetrics;
use Filament\Widgets\ChartWidget;

class EmailQuestionMisalignmentRateChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return __('admin.dashboard.misalignment_rate');
    }

    protected function getData(): array
    {
        $metrics = app(EmailQuestionDashboardMetrics::class)->dailyMisalignmentRates(30);

        return [
            'datasets' => [
                [
                    'label' => __('admin.dashboard.misalignment_rate_dataset'),
                    'data' => $metrics['misalignment_rates'],
                    'borderColor' => '#d16068',
                    'backgroundColor' => 'rgba(209, 96, 104, 0.16)',
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
