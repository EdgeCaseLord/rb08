<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AllergenResource\Pages;
use App\Filament\Resources\AllergenResource\RelationManagers;
use App\Models\Allergen;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AllergenResource extends Resource
{
    protected static ?string $model = Allergen::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'Allergene';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('code')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->searchable(),
                // Display the ingredients related to this allergen
                // Tables\Columns\TagsColumn::make('ingredients.name') // Add TagsColumn for ingredients
                //     ->label('Ingredients')
                //     ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            // Add a relation manager for `ingredients`
            // RelationManagers\IngredientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAllergens::route('/'),
            'create' => Pages\CreateAllergen::route('/create'),
            'edit' => Pages\EditAllergen::route('/{record}/edit'),
        ];
    }

    // Restrict access for admin users
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->user()?->isAdmin();
    }
}
