<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminResource\Pages;
use App\Filament\Resources\AdminResource\RelationManagers;
use App\Models\Admin;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AdminResource extends Resource
{

    protected static ?string $model = User::class;

    // Override the base query to only include users with the 'lab' role
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->admins();
    }

    public static function getModelLabel(): string
    {
        return 'Administrator';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Administratoren';
    }

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(User::class, 'email', ignoreRecord: true), // Ensure email uniqueness
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create') // required on create but optional on update
                    ->confirmed()
                    ->dehydrated(fn ($state) => filled($state)) // save password only if filled
                    ->maxLength(255),
                TextInput::make('password_confirmation')
                    ->password()
                    ->label('Confirm Password')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(false)
                    ->maxLength(255),
                Forms\Components\Hidden::make('role') // Ensure role is set to 'lab'
                ->default('admin'),
            ]);
    }

    public static function table(Table $table): Table
    {

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('lab_id')
                    ->label('Lab ID'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
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
        return auth()->check() && auth()->user()?->isAdmin();
    }

    // Customize navigation
    public static function getNavigationLabel(): string
    {
        return 'Administratoren';
    }

//    protected static ?string $navigationGroup = 'Benutzerverwaltung';

    // Restrict access to admins
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()?->isAdmin();
    }

    public static function canCreate(): bool
    {
        return auth()->check() && auth()->user()?->isAdmin();
    }

    public static function canDelete($record): bool
    {
        return auth()->check() && auth()->user()?->isAdmin();
    }
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdmins::route('/'),
            'create' => Pages\CreateAdmin::route('/create'),
            'edit' => Pages\EditAdmin::route('/{record}/edit'),
        ];
    }
}
