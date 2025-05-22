<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class TestRecipesTable extends Page
{
    // Remove from navigation
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.test-recipes-table';
    // Remove navigation label, icon, and slug
}


