<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\GmailMessages\GmailMessageResource;
use App\Models\GmailMessage;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The first thing a reviewer sees on login: the newest Gmail messages,
 * with a processed/needs-review indicator. Links through to the Gmail
 * Messages resource for the actual review pipeline (its own ViewAction
 * already handles that; a widget isn't a resource page, so it can't
 * reuse that wiring directly).
 */
class RecentGmailMessages extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent Gmail Messages')
            ->description('Newest first. Open Gmail Messages to review.')
            ->query(fn (): Builder => GmailMessageResource::getEloquentQuery()->latest('internal_date')->limit(10))
            ->columns([
                IconColumn::make('processed')
                    ->label('')
                    ->state(fn (GmailMessage $record): bool => ! $record->needsReview())
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::Clock)
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('from_email')->label('From')->limit(32),
                TextColumn::make('participant_name')->label('Participant')->placeholder('Unknown'),
                TextColumn::make('subject')->limit(60),
                TextColumn::make('internal_date')->label('Received')->dateTime(),
            ])
            ->recordUrl(fn (): string => GmailMessageResource::getUrl('index'))
            ->headerActions([
                Action::make('viewAll')
                    ->label('Open Gmail Messages')
                    ->icon(Heroicon::OutlinedInboxArrowDown)
                    ->url(fn (): string => GmailMessageResource::getUrl('index')),
            ]);
    }
}
