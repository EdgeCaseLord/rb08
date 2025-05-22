<x-filament::modal id="allergen-modal-{{ $analysis_id }}" width="5xl">
    <x-slot name="heading">
        {{ __('Allergens for Analysis') }}
    </x-slot>
    <div class="mt-4">
        @livewire('allergen-modal', ['analysis_id' => $analysis_id], key('allergen-modal-' . $analysis_id))
    </div>
</x-filament::modal>
