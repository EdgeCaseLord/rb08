<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\User;

class DoctorsWidget extends Widget
{
    protected static string $view = 'filament.widgets.stat-card';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'default' => 2,
        'md' => 1,
    ];

    // protected function getColumns(): int
    // {
    //     return 1;
    // }

    public function getData(): array
    {
        return [
            'icon' => 'heroicon-o-user-group',
            'value' => User::doctors()->count(),
            'label' => 'Ärzte',
            'resourceName' => 'Doctor',
        ];
    }
}
