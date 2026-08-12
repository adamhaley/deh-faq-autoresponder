<?php

namespace App\Filament\Resources\AuthorizedEmails;

use App\Enums\UserRole;
use App\Filament\Resources\AuthorizedEmails\Pages\ManageAuthorizedEmails;
use App\Models\AuthorizedEmail;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuthorizedEmailResource extends Resource
{
    protected static ?string $model = AuthorizedEmail::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Allowlist';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            Select::make('role')
                ->options(collect(UserRole::cases())->mapWithKeys(
                    fn (UserRole $role): array => [$role->value => ucfirst($role->value)],
                ))
                ->required()
                ->default(UserRole::Viewer->value),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role')->badge()->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuthorizedEmails::route('/'),
        ];
    }
}
