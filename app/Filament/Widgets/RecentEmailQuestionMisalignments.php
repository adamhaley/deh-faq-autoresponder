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

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent AI/Human Misalignments')
            ->query(fn (): Builder => app(EmailQuestionDashboardMetrics::class)
                ->recentMisalignmentsQuery()
                ->limit(5))
            ->columns([
                TextColumn::make('question_text')
                    ->label('Question')
                    ->limit(70),
                TextColumn::make('message.from_email')
                    ->label('From')
                    ->limit(32),
                TextColumn::make('classification')
                    ->label('AI')
                    ->badge()
                    ->color(fn (?string $state): string => EmailQuestion::classificationColor($state))
                    ->formatStateUsing(fn (?string $state): string => EmailQuestion::classificationOptions()[$state] ?? 'Unclassified'),
                TextColumn::make('review_status')
                    ->label('Human')
                    ->badge()
                    ->color(fn (string $state): string => EmailQuestion::reviewStatusColor($state))
                    ->formatStateUsing(fn (string $state): string => EmailQuestion::reviewStatusOptions()[$state] ?? $state),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
