{{-- Unified patient filter section with Alpine.js logic --}}
@props(['filterSet' => [], 'patientId', 'showApplyButton' => false])

<x-filament::section collapsible>
    <x-slot name="heading">
        Rezeptfilter
    </x-slot>
    
    <div x-data="{
        patientId: {{ $patientId }},
        filters: @js($filterSet),
        formChanged: false,
    csrf() { return document.querySelector('meta[name=csrf-token]')?.content || ''; },
    extractFiltersFromForm(form) {
        const data = new FormData(form);
        this.filters = {};
        for (const [key, value] of data.entries()) {
            const match = key.match(/^(\w+)\[(.+)\]$/);
            if (match) {
                const [, filterKey, subKey] = match;
                if (!this.filters[filterKey]) this.filters[filterKey] = {};
                this.filters[filterKey][subKey] = value === '1' || value === 'on';
            } else {
                this.filters[key] = value;
            }
        }
        this.formChanged = false;
    },
    async saveFilters() {
        if (!this.patientId) return;
        const body = { filters: this.filters || {} };
        try {
            const response = await fetch(`/admin/patients/${encodeURIComponent(this.patientId)}/filters`, {
                method: 'POST', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': this.csrf() }, body: JSON.stringify(body)
            });
            if (response.ok) {
                new FilamentNotification().title('Filter gespeichert').success().send();
            } else {
                throw new Error('Failed to save filters');
            }
        } catch (e) {
            console.error('saveFilters error:', e);
            new FilamentNotification().title('Fehler beim Speichern').danger().send();
        }
    },
    async recreateBook() {
        if (!this.patientId) return;
        new FilamentNotification().title('Buch wird neu generiert').body('Bitte warten...').info().send();
        const body = { filters: this.filters || {}, updateBookWithFilters: true };
        try {
            const response = await fetch(`/admin/patients/${encodeURIComponent(this.patientId)}/filters`, {
                method: 'POST', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': this.csrf() }, body: JSON.stringify(body)
            });
            if (response.ok) {
                new FilamentNotification().title('Buch wird generiert').success().send();
            } else {
                throw new Error('Failed to start book recreation');
            }
        } catch (e) {
            console.error('recreateBook error:', e);
            new FilamentNotification().title('Fehler beim Starten').danger().send();
        }
    }
}">
    <form x-ref="filterForm" onsubmit="return false;" @change="formChanged = true">
        @include('components.recipe-filter-form', ['filterSet' => $filterSet])

        <div class="mt-4 flex justify-end gap-2">
            @if($showApplyButton)
                <button type="button" class="px-3 py-1 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm"
                        @click="extractFiltersFromForm($refs.filterForm); applyFilters();">
                    Filter anwenden
                </button>
            @endif
            <button type="button" class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm"
                    @click="extractFiltersFromForm($refs.filterForm); saveFilters();">
                Filter speichern
            </button>
            <button type="button" class="px-3 py-1 bg-amber-600 text-white rounded hover:bg-amber-700 text-sm"
                    @click="if (formChanged) { new FilamentNotification().title('Bitte speichern Sie die Filter zuerst').danger().send(); } else { extractFiltersFromForm($refs.filterForm); recreateBook(); }">
                Buch neu generieren
            </button>
        </div>
    </form>
    </div>
</x-filament::section>
