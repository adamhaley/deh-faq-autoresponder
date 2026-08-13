<?php

namespace App\Filament\Resources\GmailMessages;

use App\Filament\Resources\GmailMessages\Pages\ManageGmailMessages;
use App\Models\GmailMessage;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GmailMessageResource extends Resource
{
    protected static ?string $model = GmailMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $navigationLabel = 'Gmail Messages';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('mailbox_email')
                    ->label('Mailbox')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('from_email')
                    ->label('From')
                    ->disabled(),
                TextInput::make('subject')
                    ->disabled(),
                TextInput::make('internal_date')
                    ->disabled(),
                Textarea::make('snippet')
                    ->disabled()
                    ->columnSpanFull(),
                Textarea::make('text_body')
                    ->label('Text body')
                    ->readOnly()
                    ->columnSpanFull(),
                Textarea::make('html_body')
                    ->label('HTML body')
                    ->readOnly()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mailbox.email')->label('Mailbox')->searchable()->sortable(),
                TextColumn::make('from_email')->label('From')->searchable()->sortable(),
                TextColumn::make('subject')->searchable()->limit(60),
                TextColumn::make('snippet')->limit(80),
                TextColumn::make('internal_date')->dateTime()->sortable(),
                TextColumn::make('imported_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('mailbox');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGmailMessages::route('/'),
        ];
    }
}
