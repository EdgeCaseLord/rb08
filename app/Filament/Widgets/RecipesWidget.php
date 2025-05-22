<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Recipe;

class RecipesWidget extends Widget
{
    protected static string $view = 'filament.widgets.stat-card';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;
//    protected int | string | array $columnSpan = 3;

    protected function getColumns(): int
    {
        return 1;
    }

    public function getData(): array
    {
        return [
            'icon' => 'heroicon-o-document',
            'value' => Recipe::count(),
            'label' => 'Rezepte',
        ];
    }
}
