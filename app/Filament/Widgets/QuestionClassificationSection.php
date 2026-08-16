<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuestionClassificationSection extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-section-heading';

    /**
     * @return array<string, string>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => __('admin.dashboard.question_classification'),
            'description' => __('admin.dashboard.question_classification_description'),
        ];
    }
}
