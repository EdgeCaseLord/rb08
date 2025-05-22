<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class LinksWidget extends Widget
{
    protected static string $view = 'filament.widgets.links';

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = 1;
//    protected int | string | array $columnSpan = 3;


    protected function getColumns(): int
    {
        return 1;
    }

    protected function getViewData(): array
    {
        $importantLinks = [
            ['title' => 'Support kontaktieren', 'url' => '#'],
            ['title' => 'Verbesserungsvorsch.', 'url' => '#'],
            ['title' => 'Website IFM-Institut', 'url' => 'https://ifm-institut.de'],
            ['title' => 'Profil verwalten', 'url' => '#'],
            ['title' => 'Datenschutz', 'url' => '#'],
        ];

        return compact('importantLinks');
    }
}
