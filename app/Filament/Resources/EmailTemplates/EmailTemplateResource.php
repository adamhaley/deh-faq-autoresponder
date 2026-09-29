<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Resources\EmailTemplates\Pages\ManageEmailTemplates;
use App\Models\EmailTemplate;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.navigation.email_templates');
    }

    public static function getModelLabel(): string
    {
        return __('admin.resources.email_template.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.email_template.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(__('admin.fields.template_name'))
                    ->required(),
                Select::make('user_id')
                    ->label(__('admin.fields.template_owner'))
                    ->helperText(__('admin.help.email_template_owner'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->default(fn (): ?int => auth()->id())
                    ->unique(ignoreRecord: true),
                TextInput::make('subject')
                    ->required()
                    ->columnSpanFull(),
                RichEditor::make('body')
                    ->label(__('admin.fields.body'))
                    ->helperText(__('admin.help.email_template_body_greeting'))
                    ->required()
                    ->mergeTags([
                        'questions' => 'Questions & answers',
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('admin.fields.template_name')),
                TextColumn::make('user.name')
                    ->label(__('admin.fields.template_owner'))
                    ->placeholder(__('admin.placeholders.shared_default_template')),
                TextColumn::make('subject')->limit(60),
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
            'index' => ManageEmailTemplates::route('/'),
        ];
    }
}
