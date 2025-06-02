<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\Analysis;

class AnalysesWidget extends Widget
{
    protected static string $view = 'filament.widgets.stat-card';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = [
        'default' => 2,
        'md' => 1,
    ];

    public function getData(): array
    {
        return [
            'icon' => 'heroicon-o-magnifying-glass',
            'value' => Analysis::count(),
            'label' => 'Analysen',
            'resourceName' => 'Analysis',
        ];
    }
}
