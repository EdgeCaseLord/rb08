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
                    ->label('Titel'),
                Forms\Components\TextInput::make('first_name')
                    ->label('Vorname'),
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
                Forms\Components\Section::make(__('Filter-Präferenzen'))
                    ->description(__('Die aktuellen Filter-Präferenzen des Patienten für Rezeptvorschläge.'))
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('settings.recipe_filter_set.filterTitle')
                            ->label(__('Titel'))
                            ->nullable(),
                        Forms\Components\TextInput::make('settings.recipe_filter_set.filterIngredients')
                            ->label(__('Zutaten'))
                            ->helperText(__('Suchlogik: UND (Leerzeichen), ODER (/), NICHT (-). Beispiel: paprika / nudeln -aprikosen'))
                            ->nullable(),
                        Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterAllergen')
                            ->label(__('Allergene'))
                            ->options([
                                'peanuts' => __('Erdnüsse'),
                                'fish' => __('Fisch'),
                                'gluten' => __('Glutenhaltiges Getreide'),
                                'egg' => __('Hühnerei'),
                                'crustaceans' => __('Krebstiere'),
                                'lupin' => __('Lupinen'),
                                'milk' => __('Milch'),
                                'nuts' => __('Schalenfrüchte'),
                                'sulphure' => __('Schwefeldioxid und Sulfit'),
                                'celery' => __('Sellerie'),
                                'mustard' => __('Senf'),
                                'sesame' => __('Sesamsamen'),
                                'soybeans' => __('Soja'),
                                'molluscs' => __('Weichtiere'),
                            ])
                            ->columns(2)
                            ->nullable()
                            ->default([]),
                        Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterCategory')
                            ->label(__('Kategorie'))
                            ->options([
                                'side_dish' => __('Beilage'),
                                'fingerfood' => __('Fingerfood'),
                                'fish' => __('Fisch & Meeresfrüchte'),
                                'meat' => __('Fleisch'),
                                'vegetables' => __('Gemüse'),
                                'drink' => __('Getränk'),
                                'cake' => __('Kuchen'),
                                'salad' => __('Salat'),
                                'soup' => __('Suppe'),
                            ])
                            ->columns(2)
                            ->nullable()
                            ->default([]),
                        Forms\Components\Select::make('settings.recipe_filter_set.filterCountry')
                            ->label(__('Länderküche'))
                            ->options([
                                'ar' => __('Argentinien'), 'au' => __('Australien'), 'be' => __('Belgien'), 'ba' => __('Bosnien-Herzegowina'), 'br' => __('Brasilien'), 'bg' => __('Bulgarien'), 'cl' => __('Chile'), 'cn' => __('China'), 'de' => __('Deutschland'), 'dk' => __('Dänemark'), 'fi' => __('Finnland'), 'fr' => __('Frankreich'), 'gr' => __('Griechenland'), 'gb' => __('Großbritannien'), 'in' => __('Indien'), 'id' => __('Indonesien'), 'ie' => __('Irland'), 'il' => __('Israel'), 'it' => __('Italien'), 'jp' => __('Japan'), 'ca' => __('Kanada'), 'hr' => __('Kroatien'), 'lv' => __('Lettland'), 'lt' => __('Litauen'), 'ma' => __('Marokko'), 'mx' => __('Mexiko'), 'mn' => __('Mongolei'), 'nz' => __('Neuseeland'), 'nl' => __('Niederlande'), 'no' => __('Norwegen'), 'pe' => __('Peru'), 'ph' => __('Philippinen'), 'pt' => __('Portugal'), 'ro' => __('Rumänien'), 'ru' => __('Russland'), 'se' => __('Schweden'), 'ch' => __('Schweiz'), 'rs' => __('Serbien'), 'sc' => __('Seychellen'), 'sg' => __('Singapur'), 'sk' => __('Slowakei'), 'si' => __('Slowenien'), 'es' => __('Spanien'), 'th' => __('Thailand'), 'cz' => __('Tschechische Republik'), 'tn' => __('Tunesien'), 'tr' => __('Türkei'), 'us' => __('USA'), 'ua' => __('Ukraine'), 'hu' => __('Ungarn'), 'vn' => __('Vietnam'), 'cy' => __('Zypern'), 'at' => __('Österreich')
                            ])
                            ->multiple()
                            ->nullable()
                            ->default([]),
                        Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterCourse')
                            ->label(__('Gang'))
                            ->options([
                                'starter' => __('Vorspeise'),
                                'main_course' => __('Hauptgericht'),
                                'dessert' => __('Dessert'),
                            ])
                            ->nullable()
                            ->default([]),
                        Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterDiets')
                            ->label(__('Ernährungsweise'))
                            ->options([
                                'biologisch' => __('Biologisch'),
                                'eifrei' => __('Eifrei'),
                                'glutenfrei' => __('Glutenfrei'),
                                'histamin-free' => __('Histaminfrei'),
                                'laktosefrei' => __('Laktosefrei'),
                                'ohne Fisch' => __('Ohne Fisch'),
                                'ohne Fleisch' => __('Ohne Fleisch'),
                                'sojafrei' => __('Sojafrei'),
                                'vegan' => __('Vegan'),
                                'vegetarisch' => __('Vegetarisch'),
                                'weizenfrei' => __('Weizenfrei'),
                                'fruktose' => __('ohne Fruktose'),
                                'alcohol-free' => __('ohne Alkohol'),
                                'vitamin_b' => __('Vitamin B'),
                                'ballaststoffe' => __('Ballaststoffe'),
                                'proteine' => __('Proteine'),
                            ])
                            ->columns(2)
                            ->nullable()
                            ->default([]),
                        Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterDifficulty')
                            ->label(__('Schwierigkeitsgrad'))
                            ->options([
                                'easy' => __('einfach'),
                                'medium' => __('mittel'),
                                'difficult' => __('schwierig'),
                            ])
                            ->nullable()
                            ->default([]),
                        Forms\Components\CheckboxList::make('settings.recipe_filter_set.filterMaxTime')
                            ->label(__('Maximale Gesamtzeit'))
                            ->options([
                                'lte_30' => __('Bis 30 Minuten'),
                                'lte_60' => __('Bis 60 Minuten'),
                                'lte_120' => __('Bis 2 Stunden'),
                                'gte_120' => __('Mehr als 2 Stunden'),
                            ])
                            ->nullable()
                            ->default([]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Vorname')
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
        ];
    }
}
