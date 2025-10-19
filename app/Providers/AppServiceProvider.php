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
        } else {
            // Only run MySQL-specific statements on MySQL
            \Illuminate\Support\Facades\DB::statement('SET SESSION sql_mode = ""');
        }

        Livewire::component('recipes-table', \App\Filament\Livewire\RecipesTable::class);
        Livewire::component('book-recipes-table', \App\Filament\Livewire\BookRecipesTable::class);
        Livewire::component('favorite-recipes-table', \App\Filament\Livewire\FavoriteRecipesTable::class);
        Livewire::component('available-recipes-table', \App\Filament\Livewire\AvailableRecipesTable::class);

         // Fix timestamp casting for job batches
         \Illuminate\Support\Facades\Schema::defaultStringLength(191);

        // Override the timestamp casting for job batches
        \Illuminate\Support\Facades\Event::listen('Illuminate\Queue\Events\JobProcessing', function ($event) {
            if (method_exists($event->job, 'payload')) {
                $payload = $event->job->payload();
                if (isset($payload['data']['command'])) {
                    try {
                        $command = unserialize($payload['data']['command']);
                        if (str_contains(get_class($command), 'Import')) {
                            // Ensure timestamps are properly formatted
                            if (isset($command->created_at) && is_numeric($command->created_at)) {
                                $command->created_at = date('Y-m-d H:i:s', $command->created_at);
                            }
                        }
                    } catch (\Exception $e) {
                        // Ignore serialization errors
                    }
                }
            }
        });
    }
}
