<x-filament-panels::page>
    @livewire('recipes-table', [
        'bookId' => null,
        'context' => 'test',
        'showActions' => true,
        'isBookRecipes' => false,
    ])
    @livewire('test-component')
</x-filament-panels::page>
