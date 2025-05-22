<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class HandbuchWidget extends Widget
{
    protected static string $view = 'filament.widgets.handbuch';

    protected static ?int $sort = 6;

    // protected int | string | array $columnSpan = 1;
//    protected int | string | array $columnSpan = 3;
protected int | string | array $columnSpan = [
    'default' => 4,
    'sm' => 4,
    'md' => 1,
    'lg' => 1,
];

    // protected function getColumns(): int
    // {
    //     return 1;
    // }


    protected function getViewData(): array
    {
        $handbuchLinks = [];
        if (auth()->check() && (auth()->user()->isAdmin() || auth()->user()->isLab())) {
            $handbuchLinks = [
                [
                    'title' => 'Patient anlegen',
                    'url' => route('filament.admin.resources.patients.create', [], false) ?: '#',
                ],
                [
                    'title' => 'Arzt anlegen',
                    'url' => route('filament.admin.resources.doctors.create', [], false) ?: '#',
                ],
                [
                    'title' => 'Analyse hochladen',
                    'url' => route('filament.admin.resources.analyses.index', false) ?: '#',
                ],
                [
                    'title' => 'Einstellungen',
                    'url' => route('filament.admin.pages.settings', [], false) ?: '#',
                ],
                [
                    'title' => 'Rezeptbuch anlegen',
                    'url' => route('filament.admin.resources.books.create', [], false) ?: '#',
                ],
            ];
        }
        // $handbuchLinks[] = [
        //     'title' => 'Benutzer verwalten',
        //     'url' => route('filament.admin.resources.users.index', [], false) ?: '#',
        // ];

        return compact('handbuchLinks');
    }
}
