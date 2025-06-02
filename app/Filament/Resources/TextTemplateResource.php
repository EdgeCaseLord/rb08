<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TextTemplateResource\Pages;
use App\Filament\Resources\TextTemplateResource\RelationManagers;
use App\Models\TextTemplate;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class TextTemplateResource extends Resource
{
    protected static ?string $model = TextTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 99;

    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || $user->isLab());
    }

    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || $user->isLab());
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || $user->isLab());
    }

    public static function canView(
        $record
    ): bool {
        $user = Auth::user();
        /** @var User|null $user */
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return $user->isLab() && $record->user_id === $user->id;
    }

    public static function canEdit(
        $record
    ): bool {
        $user = Auth::user();
        /** @var User|null $user */
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return $user->isLab() && $record->user_id === $user->id;
    }

    public static function canDelete(
        $record
    ): bool {
        $user = Auth::user();
        /** @var User|null $user */
        if (!$user) return false;
        if ($user->isAdmin()) return true;
        return $user->isLab() && $record->user_id === $user->id;
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        /** @var User|null $user */
        if ($user && $user->isLab()) {
            $query->where('user_id', $user->id);
        }
        return $query;
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();
        /** @var User|null $user */
        $isLab = $user && $user->isLab();
        $isAdmin = $user && $user->isAdmin();

        $labField = $isLab
            ? \Filament\Forms\Components\Hidden::make('user_id')
                ->default($user->id)
                ->required()
            : \Filament\Forms\Components\Select::make('user_id')
                ->label(__('Labor'))
                ->options(fn () => User::where('role', 'lab')->pluck('name', 'id'))
                ->default(fn () => User::where('role', 'lab')->orderBy('id')->first()?->id)
                ->searchable()
                ->required();

        $typeOptions = [
            'analysis_import_email' => __('Email bei Analyseimport'),
            'book_send_email' => __('Email bei Buchversand'),
            'book_text' => __('Buch Text'),
        ];

        $bookTextOptions = [
            'impressum' => __('Impressum'),
            'erlaeuterung_1' => __('Erläuterungen 1'),
            'naehrwerttabelle' => __('Nährwerttabelle'),
            'erlaeuterung_2' => __('Erläuterungen 2'),
        ];

        return $form->schema([
            \Filament\Forms\Components\Group::make([
                $labField,
                \Filament\Forms\Components\Select::make('type')
                    ->label(__('Typ'))
                    ->options($typeOptions)
                    ->required()
                    ->reactive(),
                \Filament\Forms\Components\Select::make('book_text_type')
                    ->label(__('Buch Text Typ'))
                    ->options($bookTextOptions)
                    ->required()
                    ->visible(fn (\Filament\Forms\Get $get) => $get('type') === 'book_text')
                    ->reactive(),
                \Filament\Forms\Components\Select::make('language')
                    ->label(__('Sprache'))
                    ->options([
                        'de' => 'Deutsch',
                        'en' => 'English',
                    ])
                    ->default('de')
                    ->reactive()
                    ->visible(fn(\Filament\Forms\Get $get) =>
                        ($get('type') !== 'book_text' && filled($get('type')))
                        || ($get('type') === 'book_text' && filled($get('book_text_type')))
                    )
                    ->afterStateUpdated(function ($state, $old, $set, $get) {
                        // Save current subject/body to the hidden fields for the previous language
                        $subject = $get('subject_by_language');
                        $body = $get('body_by_language');
                        if ($old === 'de') {
                            $set('subject_de', $subject);
                            $set('body_de', $body);
                        } elseif ($old === 'en') {
                            $set('subject_en', $subject);
                            $set('body_en', $body);
                        }
                        // Load new language values
                        if ($state === 'de') {
                            $set('subject_by_language', $get('subject_de') ?? '');
                            $set('body_by_language', $get('body_de') ?? '');
                        } elseif ($state === 'en') {
                            $set('subject_by_language', $get('subject_en') ?? '');
                            $set('body_by_language', $get('body_en') ?? '');
                        }
                    }),
                \Filament\Forms\Components\TextInput::make('subject_by_language')
                    ->label(fn(\Filament\Forms\Get $get) => __('Betreff') . ' (' . ($get('language') === 'de' ? 'Deutsch' : ($get('language') === 'en' ? 'English' : $get('language'))) . ')')
                    ->visible(fn (\Filament\Forms\Get $get) =>
                        in_array($get('type'), ['analysis_import_email', 'book_send_email'])
                    )
                    ->extraAttributes(['style' => 'font-size:1.2em;height:3em;'])
                    ->reactive()
                    ->afterStateHydrated(function ($component, $state, $record, $set, $get) {
                        $lang = $get('language') ?? 'de';
                        $subject = $record?->subject[$lang] ?? '';
                        $set('subject_by_language', $subject);
                    })
                    ->dehydrateStateUsing(function ($state, $get, $set, $record) {
                        $lang = $get('language') ?? 'de';
                        $subject = $get('subject') ?? [];
                        $subject[$lang] = $state;
                        $set('subject', $subject);
                        return $state;
                    }),
                \Filament\Forms\Components\RichEditor::make('body_by_language')
                    ->label(fn(\Filament\Forms\Get $get) => __('Text') . ' (' . ($get('language') === 'de' ? 'Deutsch' : ($get('language') === 'en' ? 'English' : $get('language'))) . ')')
                    ->visible(fn (\Filament\Forms\Get $get) =>
                        (filled($get('type')) && $get('type') !== 'book_text')
                        || ($get('type') === 'book_text' && filled($get('book_text_type')))
                    )
                    ->toolbarButtons([
                        'bold', 'italic', 'underline', 'strike', 'link', 'blockquote', 'codeBlock', 'h2', 'h3', 'orderedList', 'bulletList', 'undo', 'redo',
                    ])
                    ->extraAttributes(['style' => 'min-height:300px;'])
                    ->reactive()
                    ->afterStateHydrated(function ($component, $state, $record, $set, $get) {
                        $lang = $get('language') ?? 'de';
                        $body = $record?->body[$lang] ?? '';
                        $set('body_by_language', $body);
                    })
                    ->dehydrateStateUsing(function ($state, $get, $set, $record) {
                        $lang = $get('language') ?? 'de';
                        $body = $get('body') ?? [];
                        $body[$lang] = $state;
                        $set('body', $body);
                        return $state;
                    }),
                // Add hidden fields to store all language variants
                \Filament\Forms\Components\Hidden::make('subject_de')
                    ->afterStateHydrated(function ($component, $state, $record, $set) {
                        $set('subject_de', $record?->subject['de'] ?? '');
                    }),
                \Filament\Forms\Components\Hidden::make('subject_en')
                    ->afterStateHydrated(function ($component, $state, $record, $set) {
                        $set('subject_en', $record?->subject['en'] ?? '');
                    }),
                \Filament\Forms\Components\Hidden::make('body_de')
                    ->afterStateHydrated(function ($component, $state, $record, $set) {
                        $set('body_de', $record?->body['de'] ?? '');
                    }),
                \Filament\Forms\Components\Hidden::make('body_en')
                    ->afterStateHydrated(function ($component, $state, $record, $set) {
                        $set('body_en', $record?->body['en'] ?? '');
                    }),
            ])->columns(1)
            ->afterStateHydrated(function ($component, $state, $record, $set, $get) {
                // On initial load, ensure hidden fields are set for all languages
                $set('subject_de', $record?->subject['de'] ?? '');
                $set('subject_en', $record?->subject['en'] ?? '');
                $set('body_de', $record?->body['de'] ?? '');
                $set('body_en', $record?->body['en'] ?? '');
            }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            \Filament\Tables\Columns\TextColumn::make('type')
                ->label(__('Typ'))
                ->formatStateUsing(function ($state, $record) {
                    if ($state === 'book_text' && !empty($record->book_text_type)) {
                        $bookTextTypes = [
                            'impressum' => __('Impressum'),
                            'erlaeuterung_1' => __('Erläuterungen 1'),
                            'naehrwerttabelle' => __('Nährwerttabelle'),
                            'erlaeuterung_2' => __('Erläuterungen 2'),
                        ];
                        $typeLabel = __('Buch Text');
                        $bookTypeLabel = $bookTextTypes[$record->book_text_type] ?? $record->book_text_type;
                        return $typeLabel . ' (' . $bookTypeLabel . ')';
                    }
                    $typeLabels = [
                        'analysis_import_email' => __('Email bei Analyseimport'),
                        'book_send_email' => __('Email bei Buchversand'),
                        'book_text' => __('Buch Text'),
                    ];
                    return $typeLabels[$state] ?? $state;
                }),
            \Filament\Tables\Columns\TextColumn::make('subject.de')->label(__('Betreff (DE)'))->limit(40),
            \Filament\Tables\Columns\TextColumn::make('subject.en')->label(__('Betreff (EN)'))->limit(40),
            \Filament\Tables\Columns\TextColumn::make('body.de')->label(__('Text (DE)'))->limit(40),
            \Filament\Tables\Columns\TextColumn::make('body.en')->label(__('Text (EN)'))->limit(40),
        ])
        ->filters([
            //
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
        ])
        ->bulkActions([
            Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTextTemplates::route('/'),
            'create' => Pages\CreateTextTemplate::route('/create'),
            'edit' => Pages\EditTextTemplate::route('/{record}/edit'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return __('Text Templates');
    }

    public static function getModelLabel(): string
    {
        return __('Textvorlage');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Textvorlagen');
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? null) === 'book_text' && !empty($data['book_text_type'])) {
            $type = strtolower($data['book_text_type']);
            $type = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $type);
            $type = preg_replace('/[^a-z0-9_]/', '', $type);
            $data['type'] = 'book_text_' . $type;
        }
        if (empty($data['body'])) {
            $data['body'] = json_encode(new \stdClass());
        }
        if (empty($data['subject'])) {
            $data['subject'] = json_encode(new \stdClass());
        }
        return $data;
    }

    public static function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? null) === 'book_text' && !empty($data['book_text_type'])) {
            $type = strtolower($data['book_text_type']);
            $type = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $type);
            $type = preg_replace('/[^a-z0-9_]/', '', $type);
            $data['type'] = 'book_text_' . $type;
        }
        return $data;
    }

    public static function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['type']) && str_starts_with($data['type'], 'book_text_')) {
            $data['book_text_type'] = substr($data['type'], strlen('book_text_'));
            $data['type'] = 'book_text';
        }
        return $data;
    }
}
