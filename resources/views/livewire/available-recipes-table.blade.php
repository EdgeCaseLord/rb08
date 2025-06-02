<div
    x-data="{ refresh: 0 }"
    x-on:availableRecipesUpdated.window="refresh++"
>
    @php
        if (!isset($showRecipeModal)) $showRecipeModal = false;
        if (!isset($modalRecipe)) $modalRecipe = null;
    @endphp
    <div class="mb-2 p-2 bg-[#FEF0E8] rounded text-xs flex justify-end">
        <div class="text-right w-full text-[#FF6100] font-bold">
        @php
            $book = \App\Models\Book::find($bookId);
            $patient = $book ? $book->patient : null;
            $courseLabels = ['starter'=>__('Vorspeisen'),'main_course'=>__('Hauptgerichte'),'dessert'=>__('Desserts')];
            $maxPerCourse = $book ? $book->getRecipesPerCourse() : ['starter'=>5,'main_course'=>5,'dessert'=>5];
            $totals = $patient ? ($patient->recipe_totals ?? []) : [];
            $inBook = [];
            foreach($courseLabels as $course => $label) {
                $inBook[$course] = $book ? $book->recipes()->where('course', $course)->count() : 0;
            }
        @endphp
        @foreach($courseLabels as $course => $label)
            <span class="ml-4">
                {{ $label }}: {{ max(0, ($maxPerCourse[$course] ?? 0) - ($inBook[$course] ?? 0)) }}/{{ $totals[$course] ?? 0 }}
            </span>
        @endforeach
        </div>
    </div>
    <div x-data="{ open: {{ (!empty($filterTitle) || !empty($filterDifficulty) || !empty($filterCourse) || !empty($filterIngredients) || !empty($filterDiets)) ? 'true' : 'false' }}, filteringSave: false, filteringApply: false }" x-init="" class="mt-4">
        <div class="flex items-center justify-between mb-2 cursor-pointer select-none" @click="open = !open">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707l-7 7V21a1 1 0 01-1.447.894l-4-2A1 1 0 017 19v-5.293l-7-7A1 1 0 013 4z" /></svg>
                <span>{{ __('Filter') }}</span>
                @if(!empty($filterTitle) || !empty($filterDifficulty) || !empty($filterCourse) || !empty($filterIngredients) || !empty($filterDiets))
                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800">●</span>
                @endif
            </div>
            <svg :class="{'rotate-180': open}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
        </div>
        <form x-show="open" x-transition class="mb-4 grid grid-cols-1 md:grid-cols-5 gap-2 items-end" wire:submit.prevent x-ref="filterForm">
            <!-- Active filter badges (no reset button here) -->
            <div class="col-span-full flex flex-wrap gap-2 items-center mb-2">
                @foreach(['filterTitle' => __('Titel'), 'filterIngredients' => __('Zutaten')] as $key => $label)
                    @if(!empty($$key))
                        <span class="inline-flex items-center px-2 py-1 bg-primary-100 text-primary-800 rounded text-xs font-medium">
                            {{ $label }}: {{ $$key }}
                            <button type="button" class="ml-1 text-primary-600 hover:text-primary-900" wire:click="$set('{{ $key }}', '')">&times;</button>
                        </span>
                    @endif
                @endforeach
                @foreach(['filterAllergen' => __('Allergene'), 'filterCategory' => __('Kategorie'), 'filterCountry' => __('Länderküche'), 'filterCourse' => __('Gang'), 'filterDiets' => __('Ernährungsweise'), 'filterDifficulty' => __('Schwierigkeitsgrad'), 'filterMaxTime' => __('Maximale Gesamtzeit')] as $key => $label)
                    @php
                        $isArray = is_array($$key ?? null);
                        $valueMap = [];
                        if ($key === 'filterAllergen') $valueMap = [
                            'peanuts' => __('Erdnüsse'), 'fish' => __('Fisch'), 'gluten' => __('Glutenhaltiges Getreide'), 'egg' => __('Hühnerei'), 'crustaceans' => __('Krebstiere'), 'lupin' => __('Lupinen'), 'milk' => __('Milch'), 'nuts' => __('Schalenfrüchte'), 'sulphure' => __('Schwefeldioxid und Sulfit'), 'celery' => __('Sellerie'), 'mustard' => __('Senf'), 'sesame' => __('Sesamsamen'), 'soybeans' => __('Soja'), 'molluscs' => __('Weichtiere'),
                        ];
                        if ($key === 'filterCategory') $valueMap = [
                            'side_dish' => __('Beilage'), 'fingerfood' => __('Fingerfood'), 'fish' => __('Fisch & Meeresfrüchte'), 'meat' => __('Fleisch'), 'vegetables' => __('Gemüse'), 'drink' => __('Getränk'), 'cake' => __('Kuchen'), 'salad' => __('Salat'), 'soup' => __('Suppe'),
                        ];
                        if ($key === 'filterCountry') $valueMap = [
                            'ar' => __('Argentinien'), 'au' => __('Australien'), 'be' => __('Belgien'), 'ba' => __('Bosnien-Herzegowina'), 'br' => __('Brasilien'), 'bg' => __('Bulgarien'), 'cl' => __('Chile'), 'cn' => __('China'), 'de' => __('Deutschland'), 'dk' => __('Dänemark'), 'fi' => __('Finnland'), 'fr' => __('Frankreich'), 'gr' => __('Griechenland'), 'gb' => __('Großbritannien'), 'in' => __('Indien'), 'id' => __('Indonesien'), 'ie' => __('Irland'), 'il' => __('Israel'), 'it' => __('Italien'), 'jp' => __('Japan'), 'ca' => __('Kanada'), 'hr' => __('Kroatien'), 'lv' => __('Lettland'), 'lt' => __('Litauen'), 'ma' => __('Marokko'), 'mx' => __('Mexiko'), 'mn' => __('Mongolei'), 'nz' => __('Neuseeland'), 'nl' => __('Niederlande'), 'no' => __('Norwegen'), 'pe' => __('Peru'), 'ph' => __('Philippinen'), 'pt' => __('Portugal'), 'ro' => __('Rumänien'), 'ru' => __('Russland'), 'se' => __('Schweden'), 'ch' => __('Schweiz'), 'rs' => __('Serbien'), 'sc' => __('Seychellen'), 'sg' => __('Singapur'), 'sk' => __('Slowakei'), 'si' => __('Slowenien'), 'es' => __('Spanien'), 'th' => __('Thailand'), 'cz' => __('Tschechische Republik'), 'tn' => __('Tunesien'), 'tr' => __('Türkei'), 'us' => __('USA'), 'ua' => __('Ukraine'), 'hu' => __('Ungarn'), 'vn' => __('Vietnam'), 'cy' => __('Zypern'), 'at' => __('Österreich')
                        ];
                        if ($key === 'filterCourse') $valueMap = [
                            'starter' => __('Vorspeise'), 'main_course' => __('Hauptgericht'), 'dessert' => __('Dessert'),
                        ];
                        if ($key === 'filterDiets') $valueMap = [
                            'biologisch' => __('Biologisch'), 'eifrei' => __('Eifrei'), 'glutenfrei' => __('Glutenfrei'), 'histamin-free' => __('Histaminfrei'), 'laktosefrei' => __('Laktosefrei'), 'ohne Fisch' => __('Ohne Fisch'), 'ohne Fleisch' => __('Ohne Fleisch'), 'sojafrei' => __('Sojafrei'), 'vegan' => __('Vegan'), 'vegetarisch' => __('Vegetarisch'), 'weizenfrei' => __('Weizenfrei'), 'fruktose' => __('ohne Fruktose'), 'alcohol-free' => __('ohne Alkohol'),
                        ];
                        if ($key === 'filterDifficulty') $valueMap = [
                            'easy' => __('einfach'), 'medium' => __('mittel'), 'difficult' => __('schwierig'),
                        ];
                        if ($key === 'filterMaxTime') $valueMap = [
                            'lte_30' => __('Bis 30 Minuten'), 'lte_60' => __('Bis 60 Minuten'), 'lte_120' => __('Bis 2 Stunden'), 'gte_120' => __('Mehr als 2 Stunden'),
                        ];
                    @endphp
                    @if(!empty(array_filter((array)($$key ?? []))))
                        @foreach(array_keys(array_filter((array)($$key ?? []))) as $val)
                            <span class="inline-flex items-center px-2 py-1 bg-primary-100 text-primary-800 rounded text-xs font-medium">
                                {{ $label }}: {{ $valueMap[$val] ?? $val }}
                                <button type="button" class="ml-1 text-primary-600 hover:text-primary-900" wire:click="$set('{{ $key }}{{ $isArray ? '.' . $val : '' }}', {{ $isArray ? 'false' : "''" }})">&times;</button>
                            </span>
                        @endforeach
                    @endif
                @endforeach
            </div>
            <div class="col-span-full grid grid-cols-2 gap-4">
                <input type="text" placeholder="{{ __('Titel') }}" class="filament-input w-full rounded-lg" wire:model.debounce.400ms="filterTitle" wire:keydown.enter="applyFilters">
                <div class="relative flex items-center">
                    <span class="mr-2 cursor-pointer group relative align-middle">
                        <svg class="h-4 w-4 text-gray-400 inline-block" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        <div class="absolute left-1/2 z-10 hidden group-hover:block bg-white border border-gray-300 rounded shadow-lg p-2 text-xs w-72 -translate-x-1/2 mt-2">
                            <strong>{{ __('Suchlogik für Zutaten:') }}</strong><br>
                            <ul class="list-disc ml-4">
                                <li><b>{{ __('paprika / nudeln') }}</b>: {{ __('Rezepte mit Paprika oder Nudeln') }}</li>
                                <li><b>{{ __('paprika nudeln') }}</b>: {{ __('Rezepte mit Paprika und Nudeln') }}</li>
                                <li><b>{{ __('paprika -aprikosen') }}</b>: {{ __('Rezepte mit Paprika, aber ohne Aprikosen') }}</li>
                            </ul>
                            <span class="text-gray-500">{{ __('Tipp:') }} <b>/</b> {{ __('für ODER') }}, <b>-</b> {{ __('für NICHT.') }}</span>
                            <span class="text-gray-500 block mt-1">{{ __('Alle Suchlogiken (UND, ODER, NICHT) können beliebig kombiniert werden.') }}</span>
                        </div>
                    </span>
                    <input type="text" placeholder="{{ __('Zutaten (Bsp.: paprika, nudeln -aprikosen)') }}" class="filament-input w-full rounded-lg" wire:model.debounce.500ms="filterIngredients">

                </div>
            </div>
            <div class="col-span-full grid grid-cols-2 gap-4">
                <div class="flex items-center gap-2">
                    <label for="offset" class="mb-0">{{ __('Startwert') }}</label>
                    <input type="number" id="offset" min="0" class="filament-input w-24 rounded-lg" wire:model="filterOffset">
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="randomizeOffset" wire:model="filterRandomizeOffset">
                    <label for="randomizeOffset" class="mb-0">{{ __('Startwert zufällig wählen') }}</label>
                </div>
            </div>
            <!-- Collapsible filter groups -->
            <div class="col-span-full">
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Allergene') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="flex flex-wrap gap-2 mt-2">
                        @foreach([
                            'peanuts' => __('Erdnüsse'),
                            'fish' => __('Fisch'),
                            'gluten' => __('Glutenhaltiges Getreide'),
                            'egg' => __('Hühnerei'),
                            'crustaceans' => __('Krebstiere'),
                            'lupin' => __('Lupinen'),
                            'milk' => __('Milch'),
                            'nuts' => __('Schalenfrüchte'),
                            'sulphure' => __('Schwefeldioxid und Sulfit'),
                            'celery' => __('Sellerie'),
                            'mustard' => __('Senf'),
                            'sesame' => __('Sesamsamen'),
                            'soybeans' => __('Soja'),
                            'molluscs' => __('Weichtiere'),
                        ] as $key => $label)
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" wire:model="filterAllergen.{{ $key }}" value="{{ $key }}" class="form-checkbox">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Kategorie') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="flex flex-wrap gap-2 mt-2">
                        @foreach([
                            'side_dish' => __('Beilage'),
                            'fingerfood' => __('Fingerfood'),
                            'fish' => __('Fisch & Meeresfrüchte'),
                            'meat' => __('Fleisch'),
                            'vegetables' => __('Gemüse'),
                            'drink' => __('Getränk'),
                            'cake' => __('Kuchen'),
                            'salad' => __('Salat'),
                            'soup' => __('Suppe'),
                        ] as $key => $label)
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" wire:model="filterCategory.{{ $key }}" value="{{ $key }}" class="form-checkbox">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Länderküche') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="mt-2">
                        <!-- Multiselect dropdown for countries (flags can be added with a custom component in the future). This is a plain HTML select, no dependency required. -->
                        <div class="relative">
                            <select wire:model="filterCountry" multiple class="filament-input w-full rounded-lg appearance-none pr-8 bg-none">
                                @foreach([
                                    'ar' => __('Argentinien'), 'au' => __('Australien'), 'be' => __('Belgien'), 'ba' => __('Bosnien-Herzegowina'), 'br' => __('Brasilien'), 'bg' => __('Bulgarien'), 'cl' => __('Chile'), 'cn' => __('China'), 'de' => __('Deutschland'), 'dk' => __('Dänemark'), 'fi' => __('Finnland'), 'fr' => __('Frankreich'), 'gr' => __('Griechenland'), 'gb' => __('Großbritannien'), 'in' => __('Indien'), 'id' => __('Indonesien'), 'ie' => __('Irland'), 'il' => __('Israel'), 'it' => __('Italien'), 'jp' => __('Japan'), 'ca' => __('Kanada'), 'hr' => __('Kroatien'), 'lv' => __('Lettland'), 'lt' => __('Litauen'), 'ma' => __('Marokko'), 'mx' => __('Mexiko'), 'mn' => __('Mongolei'), 'nz' => __('Neuseeland'), 'nl' => __('Niederlande'), 'no' => __('Norwegen'), 'pe' => __('Peru'), 'ph' => __('Philippinen'), 'pt' => __('Portugal'), 'ro' => __('Rumänien'), 'ru' => __('Russland'), 'se' => __('Schweden'), 'ch' => __('Schweiz'), 'rs' => __('Serbien'), 'sc' => __('Seychellen'), 'sg' => __('Singapur'), 'sk' => __('Slowakei'), 'si' => __('Slowenien'), 'es' => __('Spanien'), 'th' => __('Thailand'), 'cz' => __('Tschechische Republik'), 'tn' => __('Tunesien'), 'tr' => __('Türkei'), 'us' => __('USA'), 'ua' => __('Ukraine'), 'hu' => __('Ungarn'), 'vn' => __('Vietnam'), 'cy' => __('Zypern'), 'at' => __('Österreich')
                                ] as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
        </select>
                            <!-- Note: Native multi-selects may still show browser arrows depending on OS/browser. For a perfect UI, use a JS multiselect library. -->
                        </div>
                    </div>
                </div>
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Gang') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="flex flex-wrap gap-2 mt-2">
                        @foreach([
                            'starter' => __('Vorspeise'),
                            'main_course' => __('Hauptgericht'),
                            'dessert' => __('Dessert'),
                        ] as $key => $label)
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" wire:model="filterCourse.{{ $key }}" value="{{ $key }}" class="form-checkbox">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Ernährungsweise') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="flex flex-wrap gap-2 mt-2">
                        @foreach([
                            'biologisch' => __('Biologisch'),
                            'eifrei' => __('Eifrei'),
                            'glutenfrei' => __('Glutenfrei'),
                            'histamin-free' => __('Histaminfrei'),
                            'laktosefrei' => __('Laktosefrei'),
                            'ohne Fisch' => __('Ohne Fisch'),
                            'ohne Fleisch' => __('Ohne Fleisch'),
                            'sojafrei' => __('Sojafrei'),
                            'vegan' => __('Vegan'),
                            'vegetarisch' => __('Vegetarisch'),
                            'weizenfrei' => __('Weizenfrei'),
                            'fruktose' => __('Fruktose'),
                            'alcohol-free' => __('ohne Alkohol'),
                        ] as $key => $label)
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" wire:model="filterDiets.{{ $key }}" value="{{ $key }}" class="form-checkbox">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Schwierigkeitsgrad') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="flex flex-wrap gap-2 mt-2">
                        @foreach([
                            'easy' => __('einfach'),
                            'medium' => __('mittel'),
                            'difficult' => __('schwierig'),
                        ] as $key => $label)
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" wire:model="filterDifficulty.{{ $key }}" value="{{ $key }}" class="form-checkbox">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div x-data="{ open: false }" class="mb-2">
                    <button type="button" @click="open = !open" class="w-full flex justify-between items-center py-2">
                        <span>{{ __('Maximale Gesamtzeit') }}</span>
                        <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="flex flex-wrap gap-2 mt-2">
                        @foreach([
                            'lte_30' => __('Bis 30 Minuten'),
                            'lte_60' => __('Bis 60 Minuten'),
                            'lte_120' => __('Bis 2 Stunden'),
                            'gte_120' => __('Mehr als 2 Stunden'),
                        ] as $key => $label)
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" wire:model="filterMaxTime.{{ $key }}" value="{{ $key }}" class="form-checkbox">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-span-full flex justify-between mt-2 gap-2">
                <div class="flex gap-2">
                    <button type="button"
                        :disabled="filteringSave"
                        @click="filteringSave = true; $nextTick(() => { $wire.saveFilters().then(() => filteringSave = false); })"
                        class="px-4 py-2 bg-primary-100 text-primary-800 rounded hover:bg-primary-200 flex items-center gap-2"
                        title="Das aktuelle Filter-Set wird im Benutzerprofil gespeichert und beim nächsten Buch automatisch verwendet.">
                        <span>{{ __('Filter speichern') }}</span>
                        <template x-if="filteringSave">
                            <svg class="animate-spin h-4 w-4 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        </template>
                    </button>
                </div>
                <button type="button"
                    :disabled="filteringApply"
                    @click="filteringApply = true; $nextTick(() => { $wire.applyFilters().then(() => filteringApply = false); })"
                    class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700 flex items-center gap-2">
                    <span>{{ __('Filter anwenden') }}</span>
                    <template x-if="filteringApply">
                        <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                        </svg>
                    </template>
                </button>
            </div>

    </form>
    </div>
    <hr class="my-4 border-primary-600" style="border-width:1px">
    @if(!empty($recipes))
        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:key="avail-list-{{ $refreshKey }}">
            @php
                $normalizeField = function($value) {
                    return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
                };
            @endphp
            @foreach($recipes as $recipe)
                @php
                    // Always work with array, never Eloquent object
                    if (is_object($recipe) && method_exists($recipe, 'getAttributes')) {
                        $arr = $recipe->getAttributes();
                        foreach ([
                            'category', 'diets', 'allergens', 'media', 'ingredients', 'steps', 'substances', 'images', 'time'
                        ] as $jsonField) {
                            if (isset($arr[$jsonField])) {
                                $arr[$jsonField] = $normalizeField($arr[$jsonField]);
                            }
                        }
                        $recipe = $arr;
                    } elseif (is_object($recipe)) {
                        // Convert stdClass to array safely
                        $recipe = (array) $recipe;
                    }
                    $course = null;
                    $catVal = $recipe['category'] ?? null;
                    $categories = $normalizeField($catVal);
                    $categories = array_map('strtolower', (array)$categories);
                    if (in_array('vorspeise', $categories)) {
                        $course = 'starter';
                    } elseif (in_array('hauptgericht', $categories)) {
                        $course = 'main_course';
                    } elseif (in_array('dessert', $categories)) {
                        $course = 'dessert';
                    }
                    $canAddToBook = $course && (($totals[$course] ?? 0) > 0);
                @endphp
                <div class="mb-4 break-inside-avoid">
                    <x-filament.recipe-resource.recipe-card
                        :recipe="$recipe"
                        :context="'available'"
                        :bookId="$bookId"
                        :isBookRecipes="false"
                        :showActions="true"
                        :canAddToBook="$canAddToBook"
                        wire:key="avail-recipe-{{ $recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] ?? '' }}"
                    />
                </div>
            @endforeach
        </div>
        @if($loading)
            <div class="flex justify-center py-4">
                <svg class="animate-spin h-6 w-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                </svg>
            </div>
        @endif
        @if($hasMore)
            <div class="flex justify-center py-2 text-xs text-gray-400">
                <span>perPage: {{ $perPage }}, loaded: {{ count($recipes) }}</span>
            </div>
            <div class="flex justify-center py-4">
                <div x-data="{ loadingMore: false }">
                    <button
                        type="button"
                        class="px-4 py-2 bg-primary-600 text-white font-bold rounded hover:bg-primary-700 flex items-center gap-2"
                        :disabled="loadingMore"
                        @click="loadingMore = true; $wire.loadMore().then(() => loadingMore = false);"
                    >
                        <span>Mehr laden</span>
                        <template x-if="loadingMore">
                            <svg class="animate-spin h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                        </template>
                    </button>
                </div>
            </div>
        @endif
        @if(!$hasMore && !$loading)
            <div class="text-center text-gray-400 py-2">Keine weiteren Rezepte.</div>
        @endif
    @else
        <div class="text-gray-400 py-2">Keine verfügbaren Rezepte.</div>
    @endif
    @if($showRecipeModal && $modalRecipe)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" wire:click="closeRecipeModal">
            <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl relative" wire:click.stop style="max-height:90vh; overflow-y:auto;">
                <button class="absolute top-2 right-2 text-gray-500 hover:text-gray-700" wire:click="closeRecipeModal">&times;</button>
                <div class="p-6">
                    @include('filament.resources.recipe-resource.view-recipe', ['recipe' => $modalRecipe])
                </div>
            </div>
        </div>
    @endif
</div>
