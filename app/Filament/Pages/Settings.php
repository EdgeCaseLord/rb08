<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\HtmlString;

class Settings extends Page
{
    protected static string $view = 'filament.pages.settings';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Einstellungen';

    protected static ?string $title = 'Einstellungen';

    protected static bool $shouldRegisterNavigation = false;

    public $data = [];

    public static function getNavigationUrl(): string
    {
        return url('settings');
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema($this->getFormSchema())
                ->statePath('data'),
        ];
    }

    protected function getFormSchema(): array
    {
        $schema = [
            Forms\Components\Radio::make('language')
                ->label(fn () => App::getLocale() === 'de' ? 'Language' : 'Sprache')
                ->options([
                    'de' => new HtmlString('<span class="flex items-center gap-x-2"><span>🇩🇪</span><span>Deutsch</span></span>'),
                    'en' => new HtmlString('<span class="flex items-center gap-x-2"><span>🇬🇧</span><span>English</span></span>'),
                ])
                ->required()
                ->inline()
                ->inlineLabel(false)
                ->live()
                ->afterStateUpdated(function ($state) {
                    $user = auth()->user();
                    $user->update([
                        'settings' => array_merge(
                            $user->settings ?? [],
                            ['language' => $state]
                        )
                    ]);
                    App::setLocale($state); // Set locale immediately
                    \Log::info('Settings: Language updated', ['language' => $state, 'locale' => App::getLocale()]);
                    $this->dispatch('language-changed', language: $state);
                }),
        ];

        if (auth()->user()->canEditLabSettings()) {
            $schema[] = Forms\Components\TextInput::make('threshold')
                ->label(__('threshold'))
                ->numeric()
                ->required()
                ->minValue(0)
                ->step(0.01)
                ->helperText(__('Set the threshold for allergen positivity.'))
                ->visible(fn () => auth()->user()->isLab());
        }

        if (auth()->user()->isLab() || auth()->user()->isDoctor()) {
            $schema[] = Forms\Components\Section::make(__('Recipes Per Course'))
                ->description(__('Set the number of recipes to fetch per course for recipe books.'))
                ->schema([
                    Forms\Components\TextInput::make('settings.recipes_per_course.starter')
                        ->label(__('Starter'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->placeholder(__('undefined')),
                    Forms\Components\TextInput::make('settings.recipes_per_course.main_course')
                        ->label(__('Main Course'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->placeholder(__('undefined')),
                    Forms\Components\TextInput::make('settings.recipes_per_course.dessert')
                        ->label(__('Dessert'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->placeholder(__('undefined')),
                ])
                ->columns(3);
        }

        return $schema;
    }

    public function mount(): void
    {
        $user = auth()->user();
        $this->data = [
            'language' => $user->settings['language'] ?? 'de',
            'threshold' => $user->isLab() ? $user->threshold : null,
            'settings' => [
                'recipes_per_course' => [
                    'starter' => $user->settings['recipes_per_course']['starter'] ?? null,
                    'main_course' => $user->settings['recipes_per_course']['main_course'] ?? null,
                    'dessert' => $user->settings['recipes_per_course']['dessert'] ?? null,
                ],
            ],
        ];
    }

    public function submit()
    {
        $state = $this->form->getState();
        $user = auth()->user();

        if ($user->canEditLabSettings() && $user->isLab()) {
            $user->update(['threshold' => (float) $state['threshold']]);
        }

        if ($user->isLab() || $user->isDoctor()) {
            $user->update([
                'settings' => array_merge(
                    $user->settings ?? [],
                    [
                        'language' => $state['language'], // Ensure language is updated
                        'recipes_per_course' => $state['settings']['recipes_per_course'],
                    ]
                )
            ]);
        }

        Notification::make()
            ->title('Settings saved successfully!')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Forms\Components\Actions\Action::make('save')
                ->label('Save')
                ->submit('submit')
                ->color('primary'),
        ];
    }
}
