<?php

namespace App\Filament\Resources\GmailMailboxes;

use App\Filament\Resources\GmailMailboxes\Pages\ManageGmailMailboxes;
use App\Models\GmailMailbox;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GmailMailboxResource extends Resource
{
    protected static ?string $model = GmailMailbox::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Gmail Mailboxes';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->email()
                    ->disabled(),
                Toggle::make('is_active')
                    ->label('Active'),
                TagsInput::make('label_ids')
                    ->label('Gmail labels')
                    ->placeholder('INBOX'),
                TextInput::make('import_query')
                    ->label('Import query'),
                TextInput::make('sync_status')
                    ->disabled(),
                TextInput::make('last_history_id')
                    ->disabled(),
                Textarea::make('last_error')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('sync_status')->badge()->sortable(),
                TextColumn::make('label_ids')->badge(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('last_history_id')->sortable(),
                TextColumn::make('last_sync_at')->dateTime()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('connectGmail')
                    ->label('Connect Gmail')
                    ->icon(Heroicon::OutlinedPlus)
                    ->url(route('integrations.gmail.redirect')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageGmailMailboxes::route('/'),
        ];
    }
}
