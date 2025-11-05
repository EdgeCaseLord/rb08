@php
    // Support Filament's viewData closure for filterSet
    if (is_callable($filterSet ?? null)) {
        $contextRecord = $record ?? ($this->record ?? null);
        $filterSet = $filterSet($contextRecord);
    }
    
    // Check if each filter section has active values to auto-expand
    // Handle both array format ['dessert'] and object format {dessert: true}
    $checkActive = function($val) {
        if (empty($val)) return false;
        if (is_array($val)) {
            // If indexed array, check if non-empty
            if (array_values($val) === $val) return count($val) > 0;
            // If associative array, check if any value is truthy
            return !empty(array_filter($val));
        }
        return false;
    };
    
    $hasAllergen = $checkActive($filterSet['filterAllergen'] ?? []);
    $hasCategory = $checkActive($filterSet['filterCategory'] ?? []);
    $hasCountry = !empty($filterSet['filterCountry'] ?? []);
    $hasCourse = $checkActive($filterSet['filterCourse'] ?? []);
    $hasDiets = $checkActive($filterSet['filterDiets'] ?? []);
    $hasDifficulty = $checkActive($filterSet['filterDifficulty'] ?? []);
    $hasMaxTime = $checkActive($filterSet['filterMaxTime'] ?? []);
    
    // Check if any substance filter is actually enabled
    $hasSubstances = false;
    if (!empty($filterSet['filterSubstances']) && is_array($filterSet['filterSubstances'])) {
        foreach ($filterSet['filterSubstances'] as $substance) {
            if (is_array($substance) && !empty($substance['enabled'])) {
                $hasSubstances = true;
                break;
            }
        }
    }
@endphp
<div class="mt-4" x-data="{ 
    oA: {{ $hasAllergen ? 'true' : 'false' }}, 
    oC: {{ $hasCategory ? 'true' : 'false' }}, 
    oK: {{ $hasCountry ? 'true' : 'false' }}, 
    oG: {{ $hasCourse ? 'true' : 'false' }}, 
    oD: {{ $hasDiets ? 'true' : 'false' }}, 
    oS: {{ $hasDifficulty ? 'true' : 'false' }}, 
    oT: {{ $hasMaxTime ? 'true' : 'false' }}, 
    oSub: {{ $hasSubstances ? 'true' : 'false' }} 
}">
    <div class="mb-4 grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
        <div class="col-span-full grid grid-cols-2 gap-4">
            <input type="text" placeholder="{{ __('Titel') }}" class="filament-input w-full rounded-lg" name="filterTitle" value="{{ old('filterTitle', $filterSet['filterTitle'] ?? '') }}">
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
                        <span class="text-gray-500">{{ __('Tipp:') }} <b>/</b> {{ __('für ODER') }}, <b>{{ __('Leerzeichen') }}</b> {{ __('für UND') }}, <b>-</b> {{ __('für NICHT.') }}</span>
                        <span class="text-gray-500 block mt-1">{{ __('Alle Suchlogiken (UND, ODER, NICHT) können beliebig kombiniert werden.') }}</span>
                    </div>
                </span>
                <input type="text" placeholder="{{ __('Zutaten (Bsp.: paprika nudeln -aprikosen)') }}" class="filament-input w-full rounded-lg" name="filterIngredients" value="{{ old('filterIngredients', $filterSet['filterIngredients'] ?? '') }}">
            </div>
        </div>
        <div class="col-span-full">
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oA=!oA">
                    <span>{{ __('Allergene') }}</span>
                    <svg :class="{'rotate-180': oA}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="flex flex-wrap gap-2 mt-2" x-show="oA" x-transition>
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
                            <input type="checkbox" name="filterAllergen[{{ $key }}]" value="1" class="form-checkbox" @if(old('filterAllergen.'.$key, ($filterSet['filterAllergen'][$key] ?? false))) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oK=!oK">
                    <span>{{ __('Kategorie') }}</span>
                    <svg :class="{'rotate-180': oK}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="flex flex-wrap gap-2 mt-2" x-show="oK" x-transition>
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
                            <input type="checkbox" name="filterCategory[{{ $key }}]" value="1" class="form-checkbox" @if(old('filterCategory.'.$key, ($filterSet['filterCategory'][$key] ?? false))) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oC=!oC">
                    <span>{{ __('Länderküche') }}</span>
                    <svg :class="{'rotate-180': oC}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="mt-2" x-show="oC" x-transition>
                    <div class="relative">
                        <select name="filterCountry[]" multiple class="filament-input w-full rounded-lg appearance-none pr-8 bg-none">
                            @foreach([
                                'ar' => __('Argentinien'), 'au' => __('Australien'), 'be' => __('Belgien'), 'ba' => __('Bosnien-Herzegowina'), 'br' => __('Brasilien'), 'bg' => __('Bulgarien'), 'cl' => __('Chile'), 'cn' => __('China'), 'de' => __('Deutschland'), 'dk' => __('Dänemark'), 'fi' => __('Finnland'), 'fr' => __('Frankreich'), 'gr' => __('Griechenland'), 'gb' => __('Großbritannien'), 'in' => __('Indien'), 'id' => __('Indonesien'), 'ie' => __('Irland'), 'il' => __('Israel'), 'it' => __('Italien'), 'jp' => __('Japan'), 'ca' => __('Kanada'), 'hr' => __('Kroatien'), 'lv' => __('Lettland'), 'lt' => __('Litauen'), 'ma' => __('Marokko'), 'mx' => __('Mexiko'), 'mn' => __('Mongolei'), 'nz' => __('Neuseeland'), 'nl' => __('Niederlande'), 'no' => __('Norwegen'), 'pe' => __('Peru'), 'ph' => __('Philippinen'), 'pt' => __('Portugal'), 'ro' => __('Rumänien'), 'ru' => __('Russland'), 'se' => __('Schweden'), 'ch' => __('Schweiz'), 'rs' => __('Serbien'), 'sc' => __('Seychellen'), 'sg' => __('Singapur'), 'sk' => __('Slowakei'), 'si' => __('Slowenien'), 'es' => __('Spanien'), 'th' => __('Thailand'), 'cz' => __('Tschechische Republik'), 'tn' => __('Tunesien'), 'tr' => __('Türkei'), 'us' => __('USA'), 'ua' => __('Ukraine'), 'hu' => __('Ungarn'), 'vn' => __('Vietnam'), 'cy' => __('Zypern'), 'at' => __('Österreich')
                            ] as $key => $label)
                                <option value="{{ $key }}" @if(collect(old('filterCountry', $filterSet['filterCountry'] ?? []))->contains($key)) selected @endif>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oG=!oG">
                    <span>{{ __('Gang') }}</span>
                    <svg :class="{'rotate-180': oG}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="flex flex-wrap gap-2 mt-2" x-show="oG" x-transition>
                    @foreach([
                        'starter' => __('Vorspeise'),
                        'main_course' => __('Hauptgericht'),
                        'dessert' => __('Dessert'),
                    ] as $key => $label)
                        @php
                            $courseFilter = $filterSet['filterCourse'] ?? [];
                            // Handle both array format ['dessert'] and object format {dessert: true}
                            $isChecked = is_array($courseFilter) && (in_array($key, $courseFilter) || ($courseFilter[$key] ?? false));
                        @endphp
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterCourse[{{ $key }}]" value="1" class="form-checkbox" @if(old('filterCourse.'.$key, $isChecked)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oD=!oD">
                    <span>{{ __('Ernährungsweise') }}</span>
                    <svg :class="{'rotate-180': oD}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="flex flex-wrap gap-2 mt-2" x-show="oD" x-transition>
                    @foreach([
                        'egg-free' => __('Eifrei'),
                        'gluten-free' => __('Glutenfrei'),
                        'laktose-free' => __('Laktosefrei'),
                        'fish-free' => __('Ohne Fisch'),
                        'meat-free' => __('Ohne Fleisch'),
                        'soy-free' => __('Sojafrei'),
                        'vegan' => __('Vegan'),
                        'vegetarian' => __('Vegetarisch'),
                        'wheat-free' => __('Weizenfrei'),
                        'alcohol-free' => __('Ohne Alkohol'),
                        //'biological' => __('Biologisch'),
                        'histamine-low' => __('Histaminarm'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterDiets[{{ $key }}]" value="1" class="form-checkbox" @if(old('filterDiets.'.$key, ($filterSet['filterDiets'][$key] ?? false))) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oS=!oS">
                    <span>{{ __('Schwierigkeitsgrad') }}</span>
                    <svg :class="{'rotate-180': oS}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="flex flex-wrap gap-2 mt-2" x-show="oS" x-transition>
                    @foreach([
                        'easy' => __('einfach'),
                        'medium' => __('mittel'),
                        'difficult' => __('schwierig'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterDifficulty[{{ $key }}]" value="1" class="form-checkbox" @if(old('filterDifficulty.'.$key, ($filterSet['filterDifficulty'][$key] ?? false))) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oT=!oT">
                    <span>{{ __('Maximale Gesamtzeit') }}</span>
                    <svg :class="{'rotate-180': oT}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="flex flex-wrap gap-2 mt-2" x-show="oT" x-transition>
                    @foreach([
                        'lte_30' => __('Bis 30 Minuten'),
                        'lte_60' => __('Bis 60 Minuten'),
                        'lte_120' => __('Bis 2 Stunden'),
                        'gte_120' => __('Mehr als 2 Stunden'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterMaxTime[{{ $key }}]" value="1" class="form-checkbox" @if(old('filterMaxTime.'.$key, ($filterSet['filterMaxTime'][$key] ?? false))) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2" @click="oSub=!oSub">
                    <span>{{ __('Substanzen') }}</span>
                    <svg :class="{'rotate-180': oSub}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div class="mt-2 space-y-2" x-show="oSub" x-transition>
                    @foreach([
                        'fructose' => ['label' => __('Fruktose'), 'unit' => 'mg/100g'],
                        'vitamin B1(thiamin)' => ['label' => __('Vitamin B1 (thiamin)'), 'unit' => 'mg/100g'],
                        'carbohydrates' => ['label' => __('Kohlenhydrate'), 'unit' => 'g/100g'],
                        'protein' => ['label' => __('Protein'), 'unit' => 'g/100g'],
                    ] as $key => $meta)
                        @php
                            $opVal = old('filterSubstances.' . $key . '.op', $filterSet['filterSubstances'][$key]['op'] ?? '');
                            $enabled = old('filterSubstances.' . $key, $filterSet['filterSubstances'][$key]['enabled'] ?? false) ? true : false;
                        @endphp
                        <div class="grid grid-cols-12 items-center gap-2" x-data="{ op: '{{ $opVal }}' }">
                            <div class="col-span-3 flex items-center gap-2">
                                <input type="checkbox" name="filterSubstances[{{ $key }}]" @if($enabled) checked @endif>
                                <span>{{ $meta['label'] }}</span>
                            </div>
                            <div class="col-span-3">
                                <select name="filterSubstances[{{ $key }}][op]" x-model="op" class="w-full filament-input rounded-lg">
                                    <option value="">{{ __('Wählen Sie eine Option') }}</option>
                                    <option value="lt">&lt;</option>
                                    <option value="lte">&le;</option>
                                    <option value="gt">&gt;</option>
                                    <option value="gte">&ge;</option>
                                    <option value="bw">{{ __('zwischen') }}</option>
                                    <option value="bwe">{{ __('zwischen (exkl.)') }}</option>
                                </select>
                            </div>
                            <div class="col-span-3 flex items-center gap-2">
                                <input type="number" step="any" class="filament-input w-full rounded-lg" name="filterSubstances[{{ $key }}][val1]" value="{{ old('filterSubstances.' . $key . '.val1', $filterSet['filterSubstances'][$key]['val1'] ?? '') }}" placeholder="0">
                                <span class="text-xs text-gray-500">{{ $meta['unit'] }}</span>
                            </div>
                            <div class="col-span-3 flex items-center gap-2" x-show="op==='bw' || op==='bwe'" x-transition>
                                <input type="number" step="any" class="filament-input w-full rounded-lg" name="filterSubstances[{{ $key }}][val2]" value="{{ old('filterSubstances.' . $key . '.val2', $filterSet['filterSubstances'][$key]['val2'] ?? '') }}" placeholder="Max">
                                <span class="text-xs text-gray-500">{{ $meta['unit'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
