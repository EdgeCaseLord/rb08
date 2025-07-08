@php
    // resources/views/filament/resources/patient-resource/pages/edit-patient.blade.php
    $filterSet = $record->settings['recipe_filter_set'] ?? [];
@endphp
<x-filament-panels::page>
    {{-- Render the default Filament resource form --}}
    {{ $this->form }}

    {{-- Render the filter form and handle POST --}}
    <form method="POST" action="{{ route('patients.filters.save', ['patient' => $record->id]) }}" class="mt-8" wire:ignore id="patient-filter-form" name="patient-filter-form" onsubmit="console.log('FORM SUBMITTED');">
        @csrf
        <input type="hidden" name="debug" value="1">
        <x-recipe-filter-form :filterSet="$filterSet" />
        <div class="mt-4 flex gap-2">
            <button type="submit" id="patient-filter-submit" name="patient-filter-submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 flex items-center gap-2">
                {{ __('Filter speichern') }}
            </button>
        </div>
    </form>

    {{-- Render the unified Livewire available recipes table below the main form --}}
    @php
        $latestBook = \App\Models\Book::where('patient_id', $record->id)->latest()->first();
        $latestBookId = $latestBook ? $latestBook->id : null;
    @endphp
    <div class="mt-8">
        <livewire:available-recipes-table :book-id="$latestBookId" :patient-id="$record->id" context="edit-patient" />
    </div>
</x-filament-panels::page>
