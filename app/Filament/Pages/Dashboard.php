<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Widgets\AnalysesWidget;
use App\Filament\Widgets\PatientsWidget;
use App\Filament\Widgets\DoctorsWidget;
use App\Filament\Widgets\RecipesWidget;
use App\Filament\Widgets\LatestAnalysesTable;
use App\Filament\Widgets\HandbuchWidget;
use App\Filament\Widgets\LinksWidget;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    // Override the view to use the custom template
//    protected static string $view = 'filament.pages.dashboard';

    protected int | string | array $columnSpan = 4;

    public function getColumns(): int | string | array
    {
        return 4;
    }
//            [
//            'sm' => 2,
//            'md' => 4,
//            'xl' => 4,
//        ];
//    }

    public function getWidgets(): array
    {
        return [
            PatientsWidget::class,
            AnalysesWidget::class,
            RecipesWidget::class,
            DoctorsWidget::class,
            LatestAnalysesTable::class,
            HandbuchWidget::class,
            // LinksWidget::class,
        ];
    }
}
