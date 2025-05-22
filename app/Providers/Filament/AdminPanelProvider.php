<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use App\Filament\Widgets\AnalysesWidget;
use App\Filament\Widgets\PatientsWidget;
use App\Filament\Widgets\DoctorsWidget;
use App\Filament\Widgets\RecipesWidget;
use App\Filament\Widgets\LatestAnalysesTable;
use App\Filament\Widgets\HandbuchWidget;
use App\Filament\Widgets\LinksWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
//use App\Filament\Pages\Settings;
use Filament\Navigation\MenuItem;
//use Filament\Support\Facades\FilamentAsset;
//use Filament\Support\Assets\Js;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use App\Http\Middleware\SetLocaleMiddleware;
use App\Filament\Resources\PatientResource;
use App\Filament\Resources\DoctorResource;
use Illuminate\Support\Facades\Auth;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->passwordReset()
            ->colors([
                'primary' => '#FF6100',
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(asset('images/IFM-Logo-outline.svg'))
            ->brandLogoHeight('5rem')  // ~80px
            ->favicon(asset('images/my-favicon.ico'))
            ->resources([
                PatientResource::class,
                DoctorResource::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
                \App\Filament\Pages\Settings::class,
                \App\Filament\Pages\TestRecipesTable::class,
            ])
            ->profile()
            ->userMenuItems([
                'settings' => MenuItem::make()
                    ->label(fn() => __('Einstellungen'))
                    ->url('/settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->visible(fn () => Auth::check()),
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocaleMiddleware::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    public function boot(): void

    {
        // ...

    }
}
