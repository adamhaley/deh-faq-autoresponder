<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class AnswerPerformanceSection extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-section-heading';

    /**
     * @return array<string, string>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => __('admin.dashboard.answer_performance'),
            'description' => __('admin.dashboard.answer_performance_description'),
        ];
    }
}
