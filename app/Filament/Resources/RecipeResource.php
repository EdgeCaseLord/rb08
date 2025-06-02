<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecipeResource\Pages;
use App\Models\Recipe;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Support\Facades\Auth;

class RecipeResource extends Resource
{
    protected static ?string $model = Recipe::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 4;
    public static function getModelLabel(): string { return 'Rezept'; }
    public static function getPluralModelLabel(): string { return 'Bücher'; }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('subtitle')
                    ->label('Untertitel')
                    ->maxLength(255),
                Forms\Components\TextInput::make('alttitle')
                    ->label('Alternativer Titel')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->label('Beschreibung')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('serving')
                    ->label('Portionen')
                    ->numeric(),
                Forms\Components\TextInput::make('language')
                    ->label('Sprache')
                    ->maxLength(255),
                Forms\Components\TextInput::make('difficulty')
                    ->label('Schwierigkeitsgrad')
                    ->maxLength(255),
                Forms\Components\TextInput::make('time')
                    ->label('Zubereitungszeit')
                    ->maxLength(255),
                Forms\Components\TextInput::make('diet')
                    ->label('Diät')
                    ->maxLength(255),
                Forms\Components\TextInput::make('yield_quantity_1')
                    ->label('Ergiebigkeit Menge 1')
                    ->maxLength(255),
                Forms\Components\TextInput::make('yield_quantity_2')
                    ->label('Ergiebigkeit Menge 2')
                    ->maxLength(255),
                Forms\Components\TextInput::make('yield_info')
                    ->label('Ergiebigkeit Info')
                    ->maxLength(255),
                Forms\Components\Textarea::make('category')
                    ->label('Kategorie (JSON)')
                    ->helperText('Als JSON eingeben, z. B. {"meat": "Fleisch"}'),
                Forms\Components\Textarea::make('substances')
                    ->label('Nährstoffe (JSON)')
                    ->helperText('Als JSON eingeben, z. B. {"fat,total": {"amount": 223320}}'),
                Forms\Components\Textarea::make('media')
                    ->label('Medien (JSON)')
                    ->helperText('Als JSON eingeben, z. B. {"preview": ["url"]}'),
                Forms\Components\Textarea::make('images')
                    ->label('Bilder (JSON)')
                    ->helperText('Als JSON eingeben, z. B. ["url1", "url2"]'),
                Forms\Components\Textarea::make('steps')
                    ->label('Schritte (JSON)')
                    ->helperText('Als JSON eingeben, z. B. [{"step_number": "1", "step_text": "Kochen..."}]'),
                // Forms\Components\Textarea::make('ingredients')
                //     ->label('Zutaten (JSON)')
                //     ->helperText('Als JSON eingeben, z. B. [{"product": "Butter", "quantity1": 125, "unit": "Gramm"}]'),
                Forms\Components\Textarea::make('diets')
                    ->label('Diäten (JSON)')
                    ->helperText('Als JSON eingeben, z. B. ["glutenfrei"]'),
                Forms\Components\Textarea::make('allergens')
                    ->label('Allergene (JSON)')
                    ->helperText('Als JSON eingeben, z. B. ["keine"]'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('recipe_card')
                    ->label('Bücher')
                    ->view('components.filament.recipe-resource.recipe-card')
                    ->extraAttributes(['class' => 'align-middle']),
                ViewColumn::make('actions')
                    ->label('Aktionen')
                    ->view('components.filament.recipe-resource.recipe-actions')
                    ->extraAttributes(['class' => 'align-middle w-1/4']),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading('Rezeptdetails')
                    ->form([
                        Forms\Components\TextInput::make('title')
                            ->label('Titel')
                            ->disabled(),
                        Forms\Components\TextInput::make('subtitle')
                            ->label('Untertitel')
                            ->disabled(),
                        Forms\Components\TextInput::make('serving')
                            ->label('Portionen')
                            ->disabled(),
                        Forms\Components\TextInput::make('difficulty')
                            ->label('Schwierigkeitsgrad')
                            ->disabled(),
                        Forms\Components\TextInput::make('time')
                            ->label('Zubereitungszeit')
                            ->disabled(),
                        Forms\Components\TextInput::make('diet')
                            ->label('Diät')
                            ->disabled(),
                        // Forms\Components\Textarea::make('ingredients')
                        //     ->label('Zutaten')
                        //     ->formatStateUsing(function ($state) {
                        //         $ingredients = json_decode($state, true);
                        //         if (empty($ingredients)) {
                        //             return 'Keine';
                        //         }
                        //         return collect($ingredients)->map(function ($ingredient) {
                        //             $name = $ingredient['product'] ?? 'Unbekannt';
                        //             $quantity = $ingredient['quantity1'] ?? '';
                        //             $unit = $ingredient['unit'] ?? '';
                        //             return "- $quantity $unit $name";
                        //         })->implode("\n");
                        //     })
                        //     ->disabled()
                        //     ->columnSpanFull(),
                        Forms\Components\Textarea::make('steps')
                            ->label('Schritte')
                            ->formatStateUsing(function ($state) {
                                $steps = is_string($state ?? null) ? json_decode($state, true) : (is_array($state ?? null) ? $state : []);
                                if (empty($steps)) {
                                    return 'Keine';
                                }
                                return collect($steps)->map(function ($step) {
                                    $number = $step['step_number'] ?? '';
                                    $text = $step['step_text'] ?? 'Unbekannt';
                                    return "Schritt $number: $text";
                                })->implode("\n");
                            })
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label('Massenlöschung'),
            ])
            ->contentGrid([
                'md' => 3,
                'sm' => 2,
                'xs' => 1,
            ])
            ->paginated([12, 24, 36, 48])
            ->modifyQueryUsing(fn ($query) => $query->orderBy('id', 'desc'));
    }

    public static function canCreate(): bool
    {
        return false; // TODO: Change the autogenerated stub
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecipes::route('/'),
//            'create' => Pages\CreateRecipe::route('/create'),
            'edit' => Pages\EditRecipe::route('/{record}/edit'),
            'view' => Pages\ViewRecipe::route('/{record}'),
        ];
    }
}
