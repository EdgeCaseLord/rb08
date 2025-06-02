<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PatientsWidget extends Widget
{
    protected static string $view = 'filament.widgets.stat-card';

    protected static ?int $sort = 1;

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
        try {
            $count = User::patients()->count();
        } catch (\Exception $e) {
            Log::error('PatientsWidget failed to fetch count', [
                'error' => $e->getMessage(),
            ]);
            $count = 0;
        }

        return [
            'icon' => 'heroicon-o-user',
            'value' => $count,
            'label' => 'Patienten',
            'resourceName' => 'Patient',
        ];
    }
}
