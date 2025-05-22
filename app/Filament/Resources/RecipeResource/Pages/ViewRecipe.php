<?php

namespace App\Filament\Resources\RecipeResource\Pages;

use App\Filament\Resources\RecipeResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewRecipe extends ViewRecord
{
    protected static string $resource = RecipeResource::class;

    protected static string $view = 'filament.resources.recipe-resource.view-recipe';

    // Customize the header to show the recipe title instead of "View Recipe"
    public function getHeading(): string
    {
        return $this->record->title;
    }

    // Add a header action for PDF export
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Als PDF exportieren')
                ->url(fn () => route('recipe.pdf', $this->record))
                ->color('primary'),
        ];
    }
}
