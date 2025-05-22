<x-filament-panels::page>

    @php $bookId = $record->id ?? null; @endphp
    @php
        // Hardened normalization for all recipe fields that may be JSON or array
        $normalizeField = function($value) {
            return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
        };
    @endphp
    <div class="space-y-8">
        <x-filament::section heading="Rezepte im Buch" collapsible="true" class="max-h-[80vh] overflow-y-auto">
            @livewire('book-recipes-table', ['bookId' => $bookId])
        </x-filament::section>
        <x-filament::section heading="Favoriten" collapsible="true" class="max-h-[80vh] overflow-y-auto">
            @livewire('favorite-recipes-table', ['bookId' => $bookId])
        </x-filament::section>
        <x-filament::section heading="Verfügbare Rezepte" collapsible="true" class="max-h-[80vh] overflow-y-auto">
            @livewire('available-recipes-table', ['bookId' => $bookId])
        </x-filament::section>
    </div>
</x-filament-panels::page>
