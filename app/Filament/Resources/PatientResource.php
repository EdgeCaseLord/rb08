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
        $isAdmin = $user?->isAdmin();
        $isLab = $user?->isLab();

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label(__('Email'))
                    ->email()
                    ->required()
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
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                    ->visible(fn () => Auth::user()?->isAdmin())
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
