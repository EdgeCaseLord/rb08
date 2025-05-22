<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
//        Model::unguard();

        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        Livewire::component('recipes-table', \App\Filament\Livewire\RecipesTable::class);
        Livewire::component('book-recipes-table', \App\Filament\Livewire\BookRecipesTable::class);
        Livewire::component('favorite-recipes-table', \App\Filament\Livewire\FavoriteRecipesTable::class);
        Livewire::component('available-recipes-table', \App\Filament\Livewire\AvailableRecipesTable::class);
    }
}
