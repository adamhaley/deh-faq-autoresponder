<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Filament\Resources\EmailTemplates\Pages\ManageEmailTemplates;
use App\Models\EmailTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
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
                ReplicateAction::make('duplicate')
                    ->label(__('admin.actions.duplicate_for_user'))
                    ->iconButton()
                    ->tooltip(__('admin.actions.duplicate_for_user'))
                    ->modalHeading(__('admin.actions.duplicate_for_user'))
                    ->authorize(fn (): bool => auth()->user()?->can('create', EmailTemplate::class) ?? false)
                    ->schema([
                        Select::make('user_id')
                            ->label(__('admin.fields.template_owner'))
                            ->helperText(__('admin.help.email_template_duplicate_owner'))
                            ->options(fn (): array => static::usersWithoutPersonalTemplate())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set(
                                'name',
                                User::query()->find($state)?->name,
                            )),
                        TextInput::make('name')
                            ->label(__('admin.fields.template_name'))
                            ->required(),
                    ])
                    ->mutateRecordDataUsing(fn (array $data): array => [...$data, 'user_id' => null]),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * A user can own at most one personal template, so only users without
     * one are offered as the owner of a duplicate. Labelled with the email
     * because Google display names are not always recognisable.
     *
     * @return array<int, string>
     */
    private static function usersWithoutPersonalTemplate(): array
    {
        return User::query()
            ->whereNotIn('id', EmailTemplate::query()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->email})"])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailTemplates::route('/'),
        ];
    }
}
