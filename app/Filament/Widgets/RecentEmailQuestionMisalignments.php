<?php

namespace App\Filament\Widgets;

use App\Models\EmailQuestion;
use App\Services\EmailQuestions\EmailQuestionDashboardMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentEmailQuestionMisalignments extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.delta'))
            ->query(fn (): Builder => app(EmailQuestionDashboardMetrics::class)
                ->recentMisalignmentsQuery()
                ->limit(5))
            ->columns([
                TextColumn::make('question_text')
                    ->label(__('admin.fields.question'))
                    ->limit(70),
                TextColumn::make('message.from_email')
                    ->label(__('admin.fields.from'))
                    ->limit(32),
                TextColumn::make('classification')
                    ->label(__('admin.fields.ai'))
                    ->badge()
                    ->color(fn (?string $state): string => EmailQuestion::classificationColor($state))
                    ->formatStateUsing(fn (?string $state): string => EmailQuestion::classificationOptions()[$state] ?? __('admin.statuses.classification.unclassified')),
                TextColumn::make('review_status')
                    ->label(__('admin.fields.human'))
                    ->badge()
                    ->color(fn (string $state): string => EmailQuestion::reviewStatusColor($state))
                    ->formatStateUsing(fn (string $state): string => EmailQuestion::reviewStatusOptions()[$state] ?? $state),
                TextColumn::make('reviewed_at')
                    ->label(__('admin.fields.reviewed'))
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
