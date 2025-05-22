<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Panel\Panel;

class FilamentServiceProvider extends ServiceProvider
{
    public function boot()
    {
        \Filament\Panel::make()
            ->pages([
                \App\Filament\Pages\TestRecipesTable::class,
            ]);
    }
}
