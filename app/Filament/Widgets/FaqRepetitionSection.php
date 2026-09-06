<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class FaqRepetitionSection extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-section-heading';

    /**
     * @return array<string, string>
     */
    protected function getViewData(): array
    {
        return [
            'heading' => __('admin.dashboard.faq_repetition'),
            'description' => __('admin.dashboard.faq_repetition_description'),
        ];
    }
}
