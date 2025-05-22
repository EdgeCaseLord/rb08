<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\ViewColumn;
use Illuminate\Database\Eloquent\Model;

class RecipesRelationManager extends RelationManager
{
    protected static string $relationship = 'recipes';

    protected static ?string $title = 'Recipes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Recipes');
    }

    public static function hasSubNavigation(): bool
    {
        return false;
    }

    public function getSubNavigation(): array
    {
        return [];
    }

    public function getSubNavigationGroups(): array
    {
        return [];
    }

    public function getCachedSubNavigation(): array
    {
        return [];
    }

    public function getSubNavigationPosition(): array
    {
        return [];
    }

    public function getWidgetData(): array
    {
        return [];
    }

    public function getHeader(): array
    {
        return [];
    }

    public function getHeading(): array
    {
        return [];
    }

    public function getVisibleHeaderWidgets(): array
    {
        return [];
    }

    public function getVisibleFooterWidgets(): array
    {
        return [];
    }

    public function getFooter(): array
    {
        return [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Empty, as ViewAction uses url()
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                ViewColumn::make('recipe_card')
                    ->label(__('Recipes'))
                    ->view('components.filament.recipe-resource.recipe-card')
                    ->extraAttributes(['class' => 'align-middle']),
                ViewColumn::make('actions')
                    ->label(__('Actions'))
                    ->view('components.filament.recipe-resource.recipe-actions')
                    ->extraAttributes(['class' => 'align-middle w-1/4']),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\AttachAction::make()
                //     ->label(__('Add Recipe'))
                //     ->recordSelectOptionsQuery(function ($query) {
                //         $patient = $this->ownerRecord;
                //         if (!$patient) {
                //             return $query->whereRaw('1 = 0');
                //         }
                //         return $query->whereNotIn('id_recipe', $patient->recipes()->pluck('recipes.id_recipe'));
                //     }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(__('Recipe Details'))
                    ->modalContent(fn ($record) => view('filament.resources.recipe-resource.view-recipe', ['recipe' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                Tables\Actions\DetachAction::make()
                    ->label(__('Remove Recipe'))
                    ->modalHeading(__('Remove Recipe')),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()
                    ->label(__('Remove Recipes'))
                    ->modalHeading(__('Remove Recipes')),
            ])
            ->contentGrid([
                'md' => 3,
                'sm' => 2,
                'xs' => 1,
            ]);
    }
}
