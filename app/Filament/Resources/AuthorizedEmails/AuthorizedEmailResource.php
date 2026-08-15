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

    protected static string|\BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.allowlist');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.authorized_email.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.authorized_email.plural');
    }

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
                ->label(__('admin.fields.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role')->badge()->sortable(),
                IconColumn::make('is_active')->boolean()->label(__('admin.fields.active')),
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
