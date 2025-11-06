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
        activeFilterTags() {
            const f = this.filters || {};
            const tags = [];
            const push = (k, sub, label) => tags.push({k, sub, label});
            if (f.filterTitle) push('filterTitle', null, 'Titel: ' + f.filterTitle);
            if (f.filterIngredients) push('filterIngredients', null, 'Zutaten: ' + f.filterIngredients);
            for (const [key, val] of Object.entries(f.filterAllergen || {})) {
                if (val) push('filterAllergen', key, 'Allergen: ' + key);
            }
            for (const [key, val] of Object.entries(f.filterCategory || {})) {
                if (val) push('filterCategory', key, 'Kategorie: ' + key);
            }
            for (const [key, val] of Object.entries(f.filterCourse || {})) {
                if (val) {
                    const labels = {starter: 'Vorspeise', main_course: 'Hauptgericht', dessert: 'Dessert'};
                    push('filterCourse', key, 'Gang: ' + (labels[key] || key));
                }
            }
            for (const [key, val] of Object.entries(f.filterDiets || {})) {
                if (val) push('filterDiets', key, 'Ernährung: ' + key);
            }
            for (const [key, val] of Object.entries(f.filterDifficulty || {})) {
                if (val) push('filterDifficulty', key, 'Schwierigkeit: ' + key);
            }
            if (Array.isArray(f.filterCountry)) {
                f.filterCountry.forEach(c => push('filterCountry', c, 'Land: ' + c));
            }
            return tags;
        },
        hasActiveFilters() {
            return this.activeFilterTags().length > 0;
        },
        removeTag(tag) {
            if (tag.sub) {
                if (this.filters[tag.k] && this.filters[tag.k][tag.sub]) {
                    this.filters[tag.k][tag.sub] = false;
                }
            } else {
                this.filters[tag.k] = '';
            }
            this.formChanged = true;
        },
        clearAllFilters() {
            this.filters = {
                filterTitle: '',
                filterIngredients: '',
                filterAllergen: {},
                filterCategory: {},
                filterCountry: [],
                filterCourse: {},
                filterDiets: {},
                filterDifficulty: {},
                filterMaxTime: {}
            };
            const form = this.$refs.filterForm;
            if (form) {
                form.querySelectorAll('input[type=text], input[type=search]').forEach(i => i.value = '');
                form.querySelectorAll('input[type=checkbox]').forEach(c => c.checked = false);
                form.querySelectorAll('select').forEach(s => { if (s.multiple) { for (const o of s.options) o.selected = false; } else { s.selectedIndex = 0; } });
            }
            this.formChanged = true;
        },
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
        <!-- Active filter tags -->
        <div class="py-2 flex flex-wrap gap-2">
            <template x-for="tag in activeFilterTags()" :key="tag.k + '-' + (tag.sub||'')">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200 text-xs">
                    <span x-text="tag.label"></span>
                    <button type="button" class="ml-1" @click="removeTag(tag)">×</button>
                </span>
            </template>
            <template x-if="hasActiveFilters()">
                <button type="button" class="text-xs px-2 py-0.5 rounded bg-gray-200 hover:bg-gray-300 dark:bg-gray-700 dark:hover:bg-gray-600" @click="clearAllFilters()">Alle Filter entfernen</button>
            </template>
        </div>
        
        <form x-ref="filterForm" onsubmit="return false;" @change="formChanged = true">
            @include('components.recipe-filter-form', ['filterSet' => $filterSet, 'showSearchFields' => false])

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
