@php
    // resources/views/filament/resources/patient-resource/pages/edit-patient.blade.php
    $filterSet = $record->settings['recipe_filter_set'] ?? [];

    // Convert array format ['key1', 'key2'] to object format {key1: true, key2: true} for display
    $arrayToObject = function($arr) {
        if (!is_array($arr)) return [];
        // If already an associative array, return as is
        if (array_values($arr) !== $arr) return $arr;
        // Convert indexed array to associative
        $result = [];
        foreach ($arr as $key) {
            $result[$key] = true;
        }
        return $result;
    };

    $filterKeys = ['filterAllergen', 'filterCategory', 'filterCourse', 'filterDiets', 'filterDifficulty', 'filterMaxTime', 'filterCountry'];
    foreach ($filterKeys as $key) {
        if (isset($filterSet[$key])) {
            $filterSet[$key] = $arrayToObject($filterSet[$key]);
        }
    }
@endphp
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        
        <div class="flex justify-end gap-x-3 mt-6">
            {{ $this->getSaveFormAction() }}
            {{ $this->getCancelFormAction() }}
        </div>
    </form>

    {{-- Unified filter form component (same as book edit) --}}
    <x-patient-filter-section :filterSet="$filterSet" :patientId="$record->id" :showApplyButton="false" />

    {{-- Render relation managers --}}
    <x-filament-panels::resources.relation-managers
        :active-manager="$this->activeRelationManager"
        :managers="$this->getRelationManagers()"
        :owner-record="$record"
        :page-class="static::class"
    />
</x-filament-panels::page>
