@php
    // Support Filament's viewData closure for filterSet
    if (is_callable($filterSet ?? null)) {
        $contextRecord = $record ?? ($this->record ?? null);
        $filterSet = $filterSet($contextRecord);
    }
@endphp
<div class="mt-4">
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
        <div class="col-span-full grid grid-cols-2 gap-4">
            <div class="flex items-center gap-2">
                <label for="offset" class="mb-0">{{ __('Startwert') }}</label>
                <input type="number" id="offset" min="0" class="filament-input w-24 rounded-lg" name="filterOffset" value="{{ old('filterOffset', $filterSet['filterOffset'] ?? 0) }}">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" id="randomizeOffset" name="filterRandomizeOffset" @if(old('filterRandomizeOffset', $filterSet['filterRandomizeOffset'] ?? false)) checked @endif>
                <label for="randomizeOffset" class="mb-0">{{ __('Startwert zufällig wählen') }}</label>
            </div>
        </div>
        <div class="col-span-full">
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Allergene') }}</span>
                </button>
                <div class="flex flex-wrap gap-2 mt-2">
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
                            <input type="checkbox" name="filterAllergen[]" value="{{ $key }}" class="form-checkbox" @if(collect(old('filterAllergen', $filterSet['filterAllergen'] ?? []))->contains($key)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Kategorie') }}</span>
                </button>
                <div class="flex flex-wrap gap-2 mt-2">
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
                            <input type="checkbox" name="filterCategory[]" value="{{ $key }}" class="form-checkbox" @if(collect(old('filterCategory', $filterSet['filterCategory'] ?? []))->contains($key)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Länderküche') }}</span>
                </button>
                <div class="mt-2">
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
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Gang') }}</span>
                </button>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach([
                        'starter' => __('Vorspeise'),
                        'main_course' => __('Hauptgericht'),
                        'dessert' => __('Dessert'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterCourse[]" value="{{ $key }}" class="form-checkbox" @if(collect(old('filterCourse', $filterSet['filterCourse'] ?? []))->contains($key)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Ernährungsweise') }}</span>
                </button>
                <div class="flex flex-wrap gap-2 mt-2">
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
                        'histamin-free' => __('Histaminfrei'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterDiets[]" value="{{ $key }}" class="form-checkbox" @if(collect(old('filterDiets', $filterSet['filterDiets'] ?? []))->contains($key)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Schwierigkeitsgrad') }}</span>
                </button>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach([
                        'easy' => __('einfach'),
                        'medium' => __('mittel'),
                        'difficult' => __('schwierig'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterDifficulty[]" value="{{ $key }}" class="form-checkbox" @if(collect(old('filterDifficulty', $filterSet['filterDifficulty'] ?? []))->contains($key)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Maximale Gesamtzeit') }}</span>
                </button>
                <div class="flex flex-wrap gap-2 mt-2">
                    @foreach([
                        'lte_30' => __('Bis 30 Minuten'),
                        'lte_60' => __('Bis 60 Minuten'),
                        'lte_120' => __('Bis 2 Stunden'),
                        'gte_120' => __('Mehr als 2 Stunden'),
                    ] as $key => $label)
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="filterMaxTime[]" value="{{ $key }}" class="form-checkbox" @if(collect(old('filterMaxTime', $filterSet['filterMaxTime'] ?? []))->contains($key)) checked @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="mb-2">
                <button type="button" class="w-full flex justify-between items-center py-2">
                    <span>{{ __('Substanzen') }}</span>
                </button>
                <div class="flex flex-col gap-2 mt-2">
                    @foreach([
                        'fructose' => ['label' => __('Fruktose'), 'unit' => 'mg/100g'],
                        'vitamin_B1(thiamin)' => ['label' => __('Vitamin B1 (thiamin)'), 'unit' => 'mg/100g'],
                        'carbohydrates' => ['label' => __('Kohlenhydrate'), 'unit' => 'g/100g'],
                        'protein' => ['label' => __('Protein'), 'unit' => 'g/100g'],
                    ] as $key => $config)
                        <div class="flex items-center space-x-2">
                            <input type="checkbox" name="filterSubstances[{{ $key }}][enabled]" value="1" class="form-checkbox" @if(old('filterSubstances.' . $key . '.enabled', $filterSet['filterSubstances'][$key]['enabled'] ?? false)) checked @endif>
                            <span>{{ $config['label'] }}</span>
                            <select name="filterSubstances[{{ $key }}][op]" class="form-select w-auto">
                                <option value="">-</option>
                                <option value="lt" @if(old('filterSubstances.' . $key . '.op', $filterSet['filterSubstances'][$key]['op'] ?? '') == 'lt') selected @endif>&lt; ({{ __('weniger als') }})</option>
                                <option value="lte" @if(old('filterSubstances.' . $key . '.op', $filterSet['filterSubstances'][$key]['op'] ?? '') == 'lte') selected @endif>&le; ({{ __('weniger/gleich') }})</option>
                                <option value="gt" @if(old('filterSubstances.' . $key . '.op', $filterSet['filterSubstances'][$key]['op'] ?? '') == 'gt') selected @endif>&gt; ({{ __('mehr als') }})</option>
                                <option value="gte" @if(old('filterSubstances.' . $key . '.op', $filterSet['filterSubstances'][$key]['op'] ?? '') == 'gte') selected @endif>&ge; ({{ __('mehr/gleich') }})</option>
                            </select>
                            <input type="number" name="filterSubstances[{{ $key }}][val1]" class="w-28 text-lg filament-input rounded-lg" value="{{ old('filterSubstances.' . $key . '.val1', $filterSet['filterSubstances'][$key]['val1'] ?? 0) }}" placeholder="0">
                            <span class="text-sm text-gray-600">{{ $config['unit'] }}</span>
                            <input type="number" name="filterSubstances[{{ $key }}][val2]" class="w-28 text-lg filament-input rounded-lg" placeholder="Max" value="{{ old('filterSubstances.' . $key . '.val2', $filterSet['filterSubstances'][$key]['val2'] ?? '') }}">
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
