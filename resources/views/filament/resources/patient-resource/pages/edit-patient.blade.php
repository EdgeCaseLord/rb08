@php
    // resources/views/filament/resources/patient-resource/pages/edit-patient.blade.php
@endphp
<x-filament-panels::page>
    {{-- Render the default Filament resource form --}}
    {{ $this->form }}

    {{-- Render the unified Livewire available recipes table below the main form --}}
    @php
        $latestBook = \App\Models\Book::where('patient_id', $record->id)->latest()->first();
        $latestBookId = $latestBook ? $latestBook->id : null;
    @endphp
    <div class="mt-8">
        <livewire:available-recipes-table :book-id="$latestBookId" :patient-id="$record->id" context="edit-patient" />
    </div>
</x-filament-panels::page>
