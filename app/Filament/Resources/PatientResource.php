<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PatientResource\Pages;
use App\Filament\Resources\PatientResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class PatientResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        $locale = \Illuminate\Support\Facades\App::getLocale();
        $translation = __('Patients');
        Log::info('PatientResource: Navigation label', [
            'locale' => $locale,
            'translation' => $translation,
            'file_exists' => file_exists(resource_path('lang/de.json')),
        ]);
        return $translation;
    }

    public static function getModelLabel(): string
    {
        return __('Patient');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Patients');
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();
        $query = parent::getEloquentQuery()->where('role', 'patient');

        if ($user?->isLab()) {
            $query->where('lab_id', $user->id);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = ($user instanceof \App\Models\User) ? $user->isAdmin() : false;
        $isLab = ($user instanceof \App\Models\User) ? $user->isLab() : false;

        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label(__('Titel')),
                Forms\Components\TextInput::make('first_name')
                    ->label(__('Vorname')),
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('Email'))
                    ->type('email')
                    ->placeholder('name@example.com')
                    ->email()
                    ->nullable()
                    ->unique(User::class, 'email', ignoreRecord: true),
                Forms\Components\TextInput::make('patient_code')
                    ->label(__('Patient Code'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\DatePicker::make('birthdate')
                    ->label(__('Birthdate'))
                    ->required()
                    ->maxDate(now())
                    ->validationMessages([
                        'max_date' => 'Das Geburtsdatum muss in der Vergangenheit liegen.',
                    ]),
                Forms\Components\Select::make('doctor_id')
                    ->label(__('Doctor'))
                    ->relationship('doctor', 'name')
                    ->required(),
                Forms\Components\Select::make('lab_id')
                    ->label(__('Laboratory'))
                    ->relationship('lab', 'name')
                    ->default(fn () => $isLab ? $user->id : null)
                    ->visible($isAdmin)
                    ->required(),
                Forms\Components\Hidden::make('role')
                    ->default('patient'),
                Forms\Components\Section::make(__('Rezepte Filter'))
                    ->collapsible()
                    ->schema([
                        Forms\Components\Fieldset::make('Allergene')
                            ->schema([
                                Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterAllergens')
                                    // ->label('Allergene')
                            ->options([
                                        'peanuts' => 'Erdnüsse',
                                        'fish' => 'Fisch',
                                        'gluten' => 'Glutenhaltiges Getreide',
                                        'egg' => 'Hühnerei',
                                        'crustaceans' => 'Krebstiere',
                                        'lupin' => 'Lupinen',
                                        'milk' => 'Milch',
                                        'nuts' => 'Schalenfrüchte',
                                        'sulphure' => 'Schwefeldioxid und Sulfit',
                                        'celery' => 'Sellerie',
                                        'mustard' => 'Senf',
                                        'sesame' => 'Sesamsamen',
                                        'soybeans' => 'Soja',
                                        'molluscs' => 'Weichtiere',
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3
                                    ]),
                            ]),
                        Forms\Components\Fieldset::make('Kategorie')
                            ->schema([
                                Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterCategories')
                                    ->label('Kategorie')
                            ->options([
                                        'side_dish' => 'Beilage',
                                        'fingerfood' => 'Fingerfood',
                                        'fish' => 'Fisch & Meeresfrüchte',
                                        'meat' => 'Fleisch',
                                        'vegetables' => 'Gemüse',
                                        'drink' => 'Getränk',
                                        'cake' => 'Kuchen',
                                        'salad' => 'Salat',
                                        'soup' => 'Suppe',
                            ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3
                                    ]),
                            ]),
                        Forms\Components\Fieldset::make('Diäten')
                            ->schema([
                                Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterDiets')
                                    ->label('Diäten')
                            ->options([
                                        'egg-free' => 'Eifrei',
                                        'gluten-free' => 'Glutenfrei',
                                        'laktose-free' => 'Laktosefrei',
                                        'fish-free' => 'Ohne Fisch',
                                        'meat-free' => 'Ohne Fleisch',
                                        'soy-free' => 'Sojafrei',
                                        'vegan' => 'Vegan',
                                        'vegetarian' => 'Vegetarisch',
                                        'wheat-free' => 'Weizenfrei',
                                        'alcohol-free' => 'Ohne Alkohol',
                                        'histamin-free' => 'Histaminfrei',
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3
                                    ]),
                            ]),
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterDifficulty')
                                    ->label('Schwierigkeitsgrad')
                                    ->options([
                                        'easy' => 'Einfach',
                                        'medium' => 'Mittel',
                                        'hard' => 'Schwierig',
                                    ]),
                                Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterMaxTime')
                                    ->label('Maximale Gesamtzeit')
                                    ->options([
                                        'lte_30' => 'Bis 30 Minuten',
                                        'lte_60' => 'Bis 60 Minuten',
                                        'lte_120' => 'Bis 2 Stunden',
                                        'gte_120' => 'Mehr als 2 Stunden',
                                    ]),
                            ]),
                        Forms\Components\Select::make('settings.recipe_filter_set.filterCountry')
                            ->label('Länderküche')
                            ->multiple()
                            ->options([
                                'ar' => 'Argentinien', 'au' => 'Australien', 'be' => 'Belgien', 'ba' => 'Bosnien-Herzegowina', 'br' => 'Brasilien', 'bg' => 'Bulgarien', 'cl' => 'Chile', 'cn' => 'China', 'de' => 'Deutschland', 'dk' => 'Dänemark', 'fi' => 'Finnland', 'fr' => 'Frankreich', 'gr' => 'Griechenland', 'gb' => 'Großbritannien', 'in' => 'Indien', 'id' => 'Indonesien', 'ie' => 'Irland', 'il' => 'Israel', 'it' => 'Italien', 'jp' => 'Japan', 'ca' => 'Kanada', 'hr' => 'Kroatien', 'lv' => 'Lettland', 'lt' => 'Litauen', 'ma' => 'Marokko', 'mx' => 'Mexiko', 'mn' => 'Mongolei', 'nz' => 'Neuseeland', 'nl' => 'Niederlande', 'no' => 'Norwegen', 'pe' => 'Peru', 'ph' => 'Philippinen', 'pt' => 'Portugal', 'ro' => 'Rumänien', 'ru' => 'Russland', 'se' => 'Schweden', 'ch' => 'Schweiz', 'rs' => 'Serbien', 'sc' => 'Seychellen', 'sg' => 'Singapur', 'sk' => 'Slowakei', 'si' => 'Slowenien', 'es' => 'Spanien', 'th' => 'Thailand', 'cz' => 'Tschechische Republik', 'tn' => 'Tunesien', 'tr' => 'Türkei', 'us' => 'USA', 'ua' => 'Ukraine', 'hu' => 'Ungarn', 'vn' => 'Vietnam', 'cy' => 'Zypern', 'at' => 'Österreich'
                            ]),
                        Forms\Components\Fieldset::make('Substanzen')
                            ->columns(1)
                            ->schema([
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Checkbox::make('settings.recipe_filter_set.filterSubstances.fructose.enabled')->label('Fruktose'),
                                        Forms\Components\Select::make('settings.recipe_filter_set.filterSubstances.fructose.op')
                                            ->options(['lt'=>'<','lte'=>'≤','gt'=>'>','gte'=>'≥'])
                                            ->default('lte')
                                            ->label(false),
                                        Forms\Components\TextInput::make('settings.recipe_filter_set.filterSubstances.fructose.val1')
                                            ->numeric()
                                            ->default(0)
                                            ->label(false)
                                            ->suffix('mg/100g')
                                            ->extraAttributes(['class' => '!mt-0 !self-start']),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 1,
                                        'xl' => 3
                                    ]),
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Checkbox::make('settings.recipe_filter_set.filterSubstances.vitamin_B1(thiamin).enabled')->label('Vitamin B1 (thiamin)'),
                                        Forms\Components\Select::make('settings.recipe_filter_set.filterSubstances.vitamin_B1(thiamin).op')
                                            ->options(['lt'=>'<','lte'=>'≤','gt'=>'>','gte'=>'≥'])
                                            ->default('lte')
                                            ->label(false),
                                        Forms\Components\TextInput::make('settings.recipe_filter_set.filterSubstances.vitamin_B1(thiamin).val1')
                                            ->numeric()
                                            ->default(0)
                                            ->label(false)
                                            ->suffix('mg/100g')
                                            ->extraAttributes(['class' => '!mt-0 !self-start']),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3
                                    ]),
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Checkbox::make('settings.recipe_filter_set.filterSubstances.carbohydrates.enabled')->label('Kohlenhydrate'),
                                        Forms\Components\Select::make('settings.recipe_filter_set.filterSubstances.carbohydrates.op')
                                            ->options(['lt'=>'<','lte'=>'≤','gt'=>'>','gte'=>'≥'])
                                            ->default('lte')
                                            ->label(false),
                                        Forms\Components\TextInput::make('settings.recipe_filter_set.filterSubstances.carbohydrates.val1')
                                            ->numeric()
                                            ->default(0)
                                            ->label(false)
                                            ->suffix('g/100g')
                                            ->extraAttributes(['class' => '!mt-0 !self-start']),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3
                                    ]),
                                Forms\Components\Grid::make(3)
                                    ->schema([
                                        Forms\Components\Checkbox::make('settings.recipe_filter_set.filterSubstances.protein.enabled')->label('Protein'),
                                        Forms\Components\Select::make('settings.recipe_filter_set.filterSubstances.protein.op')
                                            ->options(['lt'=>'<','lte'=>'≤','gt'=>'>','gte'=>'≥'])
                                            ->default('lte')
                                            ->label(false),
                                        Forms\Components\TextInput::make('settings.recipe_filter_set.filterSubstances.protein.val1')
                                            ->numeric()
                                            ->default(0)
                                            ->label(false)
                                            ->suffix('g/100g')
                                            ->extraAttributes(['class' => '!mt-0 !self-start']),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'xl' => 3
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Titel'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('first_name')
                    ->label(__('Vorname'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('email')
                    ->label(__('Email'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient_code')
                    ->label(__('Patient Code'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('birthdate')
                    ->label(__('Birthdate'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('doctor.name')
                    ->label(__('Doctor'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('lab.name')
                    ->label(__('Laboratory'))
                    ->sortable()
                    ->searchable()
                    ->visible(fn () => (Auth::user() instanceof \App\Models\User) && Auth::user()->isAdmin())
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('Edit')),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('Delete')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\AnalysesRelationManager::class,
            RelationManagers\AllergensRelationManager::class,
            // RelationManagers\RecipesRelationManager::class,
            RelationManagers\BooksRelationManager::class,
        ];
    }

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

    public static function canView($record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || ($user->isLab() && $user->id === $record->lab_id));
    }

    public static function canEdit($record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || ($user->isLab() && $user->id === $record->lab_id));
    }

    public static function canDelete($record): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || ($user->isLab() && $user->id === $record->lab_id));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPatients::route('/'),
            'create' => Pages\CreatePatient::route('/create'),
            'edit' => Pages\EditPatient::route('/{record}/edit'),
            'view' => Pages\ViewPatient::route('/{record}'),
        ];
    }
}
