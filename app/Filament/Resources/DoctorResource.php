<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DoctorResource\Pages;
use App\Filament\Resources\DoctorResource\RelationManagers;
use App\Models\Doctor;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeDoctor;
use Illuminate\Database\Eloquent\Model;

class DoctorResource extends Resource
{
    protected static ?string $model = User::class;

    // Override the base query to only include users with the 'lab' role
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->doctors();
    }

    public static function getModelLabel(): string
    {
        return 'Arzt';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Ärzte';
    }

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user?->isAdmin();
        $isLab = $user?->isLab();

        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create' && $isAdmin)
                    ->confirmed()
                    ->dehydrated(fn ($state) => filled($state))
                    ->maxLength(255)
                    ->visible($isAdmin),
                TextInput::make('password_confirmation')
                    ->password()
                    ->label('Confirm Password')
                    ->required(fn (string $operation): bool => $operation === 'create' && $isAdmin)
                    ->dehydrated(false)
                    ->maxLength(255)
                    ->visible($isAdmin),
                Forms\Components\Select::make('lab_id')
                    ->label('Laboratory')
                    ->relationship('lab', 'name')
                    ->default(fn () => $isLab ? $user->id : null)
                    ->visible($isAdmin)
                    ->required(),
                Forms\Components\Hidden::make('role')
                    ->default('doctor'),
            ]);
    }

    public static function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        // Set doctor_id to the new doctor's ID (will be set after creation)
        $data['doctor_id'] = null;

        // Set lab_id based on the logged-in user
        if ($user?->isLab()) {
            $data['lab_id'] = $user->id;
        } elseif ($user?->isAdmin()) {
            $firstLab = User::labs()->first();
            if (!$firstLab) {
                throw new \Exception('No laboratory found in the system. Please create a laboratory first.');
            }
            $data['lab_id'] = $firstLab->id;
        }

        return $data;
    }

    public static function afterCreate(Model $record): void
    {
        // Set the doctor_id to themselves after creation
        $record->update(['doctor_id' => $record->id]);
    }

    public static function table(Table $table): Table
    {
        /** @var User|null $user */
        $user = Auth::user();
        $isAdmin = $user?->isAdmin();
        $isLab = $user?->isLab();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lab.name')
                    ->label('Laboratory')
                    ->sortable()
                    ->searchable()
                    ->visible($isAdmin),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(function ($record) use ($user, $isAdmin, $isLab) {
                        if ($isAdmin) return true;
                        if ($isLab && $record->lab_id === $user->id) return true;
                        return false;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function ($record) use ($user, $isAdmin, $isLab) {
                        if ($isAdmin) return true;
                        if ($isLab && $record->lab_id === $user->id) return true;
                        return false;
                    }),
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

    // Control navigation registration
    public static function shouldRegisterNavigation(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();
        return $user && ($user->isAdmin() || $user->isLab());
    }

    // Customize navigation
    public static function getNavigationLabel(): string
    {
        return 'Ärzte';
    }

//    protected static ?string $navigationGroup = 'Benutzerverwaltung';

    // Restrict access to admins
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
            'index' => Pages\ListDoctors::route('/'),
            'create' => Pages\CreateDoctor::route('/create'),
            'edit' => Pages\EditDoctor::route('/{record}/edit'),
        ];
    }
}
