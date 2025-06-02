<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Book;

class RecipesWidget extends Widget
{
    protected static string $view = 'filament.widgets.stat-card';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = [
        'default' => 2,
        'md' => 1,
    ];

    // protected int | string | array $columnSpan = 3;
    // protected function getColumns(): int
    // {
    //     return 1;
    // }

    public function getData(): array
    {
        return [
            'icon' => 'heroicon-o-document',
            'value' => Book::count(),
            'label' => 'Bücher',
            'resourceName' => 'Book',
        ];
    }
}
