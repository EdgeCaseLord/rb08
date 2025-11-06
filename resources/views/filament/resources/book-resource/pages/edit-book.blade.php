<x-filament-panels::page>

    @php $bookId = $record->id ?? null; @endphp
    @php
        // Hardened normalization for all recipe fields that may be JSON or array
        $normalizeField = function($value) {
            return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
        };
        $book = $record ?? null;
        $isEdit = !empty($book) && !empty($book->id);
        $patient = $book ? $book->patient : null;
        // Try to get the latest analysis for the patient
        $analysis = $patient ? ($patient->analyses()->latest('created_at')->first()) : null;

        // Get saved filter set from patient settings
        $patientSettings = $patient ? (is_array($patient->settings) ? $patient->settings : json_decode($patient->settings ?? '{}', true)) : [];
        $savedFilterSet = $patientSettings['recipe_filter_set'] ?? [];
        $serverFilterSet = [
            'filterTitle' => $savedFilterSet['filterTitle'] ?? '',
            'filterIngredients' => $savedFilterSet['filterIngredients'] ?? '',
            'filterAllergen' => $savedFilterSet['filterAllergen'] ?? [],
            'filterCategory' => $savedFilterSet['filterCategory'] ?? [],
            'filterCountry' => $savedFilterSet['filterCountry'] ?? [],
            'filterCourse' => $savedFilterSet['filterCourse'] ?? [],
            'filterDiets' => $savedFilterSet['filterDiets'] ?? [],
            'filterDifficulty' => $savedFilterSet['filterDifficulty'] ?? [],
            'filterMaxTime' => $savedFilterSet['filterMaxTime'] ?? [],
            'filterSubstances' => $savedFilterSet['filterSubstances'] ?? [],
        ];

        \Log::debug('Loading patient filters on page load', [
            'patient_id' => $patient ? $patient->id : null,
            'savedFilterSet' => $savedFilterSet,
            'serverFilterSet' => $serverFilterSet,
        ]);
    @endphp
    <div class="space-y-8">
        <div class="mb-6">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow p-6 flex flex-col md:flex-row md:items-center md:gap-8 gap-4">
                <div class="flex-1">
                    <form method="POST" action="{{ $isEdit ? route('book.update', ['book' => $book->id]) : \App\Filament\Resources\BookResource::getUrl('create') }}">
                        @csrf
                        @if($isEdit)
                            <input type="hidden" name="_method" value="PUT">
                        @endif
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Titel') }}</label>
                                <input type="text" name="title" value="{{ old('title', $book->title ?? '') }}" class="filament-input w-full rounded-lg text-lg py-3" required />
                            </div>
                            <div class="flex flex-row flex-shrink-0 gap-4 items-end justify-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Patient') }}</label>
                                @if($isEdit)
                                    @if($patient)
                                        <a href="{{ route('filament.admin.resources.patients.edit', $patient->id) }}" class="text-primary-600 underline" target="_blank">{{ $patient->name }}</a>
                                    @else
                                        <div class="py-2">-</div>
                                    @endif
                                @else
                                    <select name="patient_id" class="filament-input w-full rounded-lg" required>
                                        <option value="">{{ __('Bitte wählen') }}</option>
                                        @foreach(\App\Models\User::where('role', 'patient')->get() as $p)
                                            <option value="{{ $p->id }}" @if(old('patient_id', $book->patient_id ?? null) == $p->id) selected @endif>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Analyse') }}</label>
                                @php $bookAnalysis = $book && $book->analysis ? $book->analysis : $analysis; @endphp
                                @if($bookAnalysis)
                                    <a href="{{ route('filament.admin.resources.analyses.edit', $bookAnalysis->id) }}" class="text-primary-600 underline" target="_blank">
                                        {{ $bookAnalysis->sample_code ?? (__('Analyse') . ' #' . $bookAnalysis->id) }}
                                    </a>
                                @else
                                    <span class="py-2 text-gray-400">{{ __('Keine Analyse gefunden') }}</span>
                                @endif
                            </div>
                            <div x-data="{ status: @js($book->status ?? 'Warten auf Versand') }"
                                 x-init="window.addEventListener('bookStatusUpdated', e => { if (e.detail.id == @js($book->id)) status = e.detail.status });">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Status') }}</label>
                                @php
                                    $statusColors = [
                                        'Versendet' => 'bg-green-100 text-green-800',
                                        'Warten auf Versand' => 'bg-blue-100 text-blue-800',
                                        'Geändert nach Versand' => 'bg-yellow-100 text-yellow-800',
                                    ];
                                @endphp
                                <span :class="{
                                    'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': status === 'Versendet',
                                    'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': status === 'Warten auf Versand',
                                    'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': status === 'Geändert nach Versand',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200': !['Versendet','Warten auf Versand','Geändert nach Versand'].includes(status)
                                }" class="inline-block px-3 py-1 rounded-full text-xs font-semibold" x-text="status"></span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">{{ $isEdit ? __('Speichern') : __('Buch anlegen') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div wire:ignore>
            @include('filament.resources.book-resource.pages.edit-book-recipes', [
                'bookId' => $bookId,
                'bookRecipeCounts' => ['starter' => 0, 'main_course' => 0, 'dessert' => 0],
                'recipeLimits' => $book->getRecipesPerCourse() ?? ['starter' => 5, 'main_course' => 5, 'dessert' => 5],
            ])
        </div>
    </div>
    <script>
    function recipeManager(initial) {
        // Initialize filters from saved filters or defaults
        const savedFilters = initial.savedFilters || {};

        // Helper to convert array format ['dessert'] to object format {dessert: true}
        const arrayToObj = (val) => {
            if (Array.isArray(val)) {
                const obj = {};
                val.forEach(k => { if (k) obj[k] = true; });
                return obj;
            }
            return val || {};
        };

        const initialFilters = {
            filterTitle: savedFilters.filterTitle || '',
            filterIngredients: savedFilters.filterIngredients || '',
            filterAllergen: arrayToObj(savedFilters.filterAllergen),
            filterCategory: arrayToObj(savedFilters.filterCategory),
            filterCountry: savedFilters.filterCountry || [],
            filterCourse: arrayToObj(savedFilters.filterCourse),
            filterDiets: arrayToObj(savedFilters.filterDiets),
            filterDifficulty: arrayToObj(savedFilters.filterDifficulty),
            filterMaxTime: arrayToObj(savedFilters.filterMaxTime),
            filterSubstances: savedFilters.filterSubstances || {}
        };

        // Check if any filters are active
        const hasFilters = initialFilters.filterTitle || initialFilters.filterIngredients ||
            (Array.isArray(initialFilters.filterCountry) && initialFilters.filterCountry.length) ||
            Object.keys(initialFilters.filterAllergen || {}).some(k => initialFilters.filterAllergen[k]) ||
            Object.keys(initialFilters.filterCategory || {}).some(k => initialFilters.filterCategory[k]) ||
            Object.keys(initialFilters.filterCourse || {}).some(k => initialFilters.filterCourse[k]) ||
            Object.keys(initialFilters.filterDiets || {}).some(k => initialFilters.filterDiets[k]) ||
            Object.keys(initialFilters.filterDifficulty || {}).some(k => initialFilters.filterDifficulty[k]) ||
            Object.keys(initialFilters.filterMaxTime || {}).some(k => initialFilters.filterMaxTime[k]) ||
            Object.keys(initialFilters.filterSubstances || {}).length > 0;

        return {
            // state
            bookRecipes: initial.bookRecipes || [],
            favoriteRecipes: initial.favoriteRecipes || [],
            availableRecipes: initial.availableRecipes || [],
            bookId: initial.bookId,
            patientId: initial.patientId,
            recipeLimits: initial.recipeLimits || { starter: 5, main_course: 5, dessert: 5 },
            openFilters: hasFilters, // Open if filters are active
            // filters (legacy-compatible keys)
            filters: initialFilters,
            formChanged: false,
            // pagination
            perPage: 6,
            perPageAvail: 6,
            bookPage: 1,
            favPage: 1,
            availPage: 1,
            // totals
            bookTotal: 0,
            favTotal: 0,
            availTotal: 0,
            // loading guards
            isLoadingFav: false,
            isLoadingAvail: false,
            init() {
                this.updateAvailPerPage();
                window.addEventListener('resize', () => this.updateAvailPerPage());
                this.loadBookPage(1);
                this.loadFavPage(1);
                this.loadAvailPage(1);
            },
            // helpers
            idOf(r) { return r?.id_recipe || r?.id_external || r?.id || null },
            pages(list, per) { const n = Math.max(1, Math.ceil(((list||[]).length) / per)); return n },
            pageLabel(list, page, per) { const total = (list||[]).length; const p = this.pages(list, per); return `Seite ${Math.min(page,p)}/${p} · ${total} Rezepte` },
            pagesTotal(per, total) { return Math.max(1, Math.ceil((total||0)/per)); },
            pageLabelTotal(page, per, total) { const p = this.pagesTotal(per,total); return `Seite ${Math.min(page,p)}/${p} · ${total||0} Rezepte`; },
            rangeLabel(page, per, total) {
                total = total || 0; per = per || 0; page = Math.max(1, page||1);
                if (total === 0 || per === 0) return '0–0 / 0 Rezepte';
                const start = (page - 1) * per + 1;
                const end = Math.min(total, start + per - 1);
                return `${start}–${end} / ${total} Rezepte`;
            },
            csrf() { return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
            buildFilterParams() {
                const p = new URLSearchParams();
                const f = this.filters || {};
                if (f.filterTitle) p.append('filterTitle', f.filterTitle);
                if (f.filterIngredients) p.append('filterIngredients', f.filterIngredients);
                const appendBoolDict = (obj, key) => {
                    if (!obj || typeof obj !== 'object') return;
                    for (const k of Object.keys(obj)) { if (obj[k]) p.append(`${key}[${k}]`, '1'); }
                };
                const appendArray = (arr, key) => {
                    if (!Array.isArray(arr)) return;
                    for (const v of arr) p.append(`${key}[]`, v);
                };
                appendBoolDict(f.filterAllergen, 'filterAllergen');
                appendBoolDict(f.filterCategory, 'filterCategory');
                appendArray(f.filterCountry, 'filterCountry');
                appendBoolDict(f.filterCourse, 'filterCourse');
                appendBoolDict(f.filterDiets, 'filterDiets');
                appendBoolDict(f.filterDifficulty, 'filterDifficulty');
                appendBoolDict(f.filterMaxTime, 'filterMaxTime');
                // substances: { key: { enabled, op, val1, val2 } }
                if (f.filterSubstances && typeof f.filterSubstances === 'object') {
                    for (const k of Object.keys(f.filterSubstances)) {
                        const s = f.filterSubstances[k];
                        if (!s || !s.enabled) continue;
                        if (s.op) p.append(`filterSubstances[${k}][op]`, s.op);
                        if (s.val1 != null && s.val1 !== '') p.append(`filterSubstances[${k}][val1]`, String(s.val1));
                        if (s.val2 != null && s.val2 !== '') p.append(`filterSubstances[${k}][val2]`, String(s.val2));
                        p.append(`filterSubstances[${k}][enabled]`, '1');
                    }
                }
                return p.toString();
            },
            categories(list) {
                const arr = Array.isArray(list) ? list : [];
                const isTrueString = (s) => typeof s === 'string' && (/^true$/i.test(s) || /^false$/i.test(s));
                const isPlaceholder = (s) => typeof s === 'string' && s.trim().startsWith(':');
                const pick = (obj) => obj.name || obj.label || obj.title || obj.category || '';
                const out = [];
                for (const item of arr) {
                    if (typeof item === 'string') { const s=item.trim(); if (s && s.toLowerCase()!== 'value' && !isTrueString(s) && !isPlaceholder(s)) out.push(s); continue; }
                    if (item && typeof item === 'object') { const v = pick(item); if (v && !isTrueString(v) && !isPlaceholder(v)) out.push(v); }
                }
                return Array.from(new Set(out.filter(Boolean)));
            },
            labels(list) {
                const arr = Array.isArray(list) ? list : (list && typeof list === 'object' ? [list] : []);
                const isTrueString = (s) => typeof s === 'string' && (/^true$/i.test(s) || /^false$/i.test(s));
                const isPlaceholder = (s) => typeof s === 'string' && s.trim().startsWith(':');
                const fromDictBooleans = (dict) => Object.keys(dict||{}).filter(k => dict[k] === true).join(', ');
                const takeName = (obj) => obj.name || obj.label || obj.title || obj.allergen || obj.diet || '';
                let out = [];
                let truthy = [];
                if (arr.length === 1 && typeof arr[0] === 'object' && !Array.isArray(arr[0])) {
                    const dict = fromDictBooleans(arr[0]);
                    if (dict) truthy.push(...dict.split(', '));
                }
                for (const item of arr) {
                    if (typeof item === 'string') { const s=item.trim(); if (s && s.toLowerCase()!=='value' && !isTrueString(s) && !isPlaceholder(s)) out.push(s); continue; }
                    if (typeof item === 'boolean') { continue; }
                    if (Array.isArray(item)) { continue; }
                    if (item && typeof item === 'object') {
                        // Special case: { allergen: 'X', value: true }
                        if ((Object.prototype.hasOwnProperty.call(item,'allergen') || Object.prototype.hasOwnProperty.call(item,'diet')) && Object.prototype.hasOwnProperty.call(item,'value')) {
                            const k = (item.allergen || item.diet || '').toString().trim();
                            if (k && item.value === true) { truthy.push(k); continue; }
                        }
                        const named = takeName(item);
                        if (named && named.toLowerCase()!=='value' && !isTrueString(named) && !isPlaceholder(named)) { out.push(named); continue; }
                        const dict = fromDictBooleans(item);
                        if (dict) truthy.push(...dict.split(', '));
                    }
                }
                const uniqTruthy = Array.from(new Set(truthy.filter(Boolean)));
                if (uniqTruthy.length) {
                    const MAX = 12;
                    return uniqTruthy.length > MAX ? `${uniqTruthy.slice(0, MAX).join(', ')} …` : uniqTruthy.join(', ');
                }
                // If there are no truthy-labelled entries, suppress raw string lists and show 'Keine'
                return 'Keine';
            },
            bookCourseCounts: { starter: 0, main_course: 0, dessert: 0 },
            async computeBookCourseCounts() {
                // Fetch ALL recipes to count by course, not just current page
                if (!this.bookId) {
                    this.bookCourseCounts = { starter: 0, main_course: 0, dessert: 0 };
                    return;
                }
                try {
                    const resp = await fetch(`/books/${this.bookId}/recipes.json?page=1&perPage=999&_=${Date.now()}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' });
                    if (resp.ok) {
                        const data = await resp.json();
                        const counts = { starter: 0, main_course: 0, dessert: 0 };
                        (data.items||[]).forEach(r => {
                            const course = r.course;
                            if (course && counts.hasOwnProperty(course)) {
                                counts[course]++;
                            }
                        });
                        this.bookCourseCounts = counts;
                    }
                } catch (e) {
                    console.error('Failed to compute course counts:', e);
                }
            },
            // data loaders (JSON, minimal fields)
            async loadBookPage(page) {
                if (!this.bookId) { this.bookRecipes = []; this.bookTotal = 0; return; }
                page = Math.max(1, page);
                const resp = await fetch(`/books/${this.bookId}/recipes.json?page=${page}&perPage=${this.perPage}&_=${Date.now()}`,
                    { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' });
                if (resp.ok) {
                    const data = await resp.json();
                    this.bookRecipes = data.items || [];
                    this.bookTotal = data.total || 0;
                    this.bookPage = data.page || page;
                    this.computeBookCourseCounts();
                }
            },
            async loadFavPage(page) {
                if (this.isLoadingFav) return; this.isLoadingFav = true;
                try {
                    page = Math.max(1, page);
                    const resp = await fetch(`/favorites.json?page=${page}&perPage=${this.perPage}&_=${Date.now()}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.favoriteRecipes = [...(data.items || [])];
                        this.favTotal = data.total || 0;
                        this.favPage = data.page || page;
                    }
                } finally { this.isLoadingFav = false; }
            },
            async loadAvailPage(page) {
                if (this.isLoadingAvail) return; this.isLoadingAvail = true;
                try {
                    page = Math.max(1, page);
                    const qs = this.buildFilterParams();
                    const resp = await fetch(`/available.json?page=${page}&perPage=${this.perPageAvail}&${qs}&_=${Date.now()}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.availableRecipes = [...(data.items || [])];
                        this.availTotal = data.total || 0;
                        this.availPage = data.page || page;
                    }
                } finally { this.isLoadingAvail = false; }
            },
            applyFilters() { this.loadAvailPage(1); },
            updateAvailPerPage() {
                const isSmall = window.matchMedia('(max-width: 640px)').matches;
                const newPer = isSmall ? 3 : 6;
                if (newPer !== this.perPageAvail) {
                    this.perPageAvail = newPer;
                    this.loadAvailPage(this.availPage);
                }
            },
            // filter helper functions
            activeFilterTags() {
                const f = this.filters || {};
                const tags = [];
                const push = (k, sub, label) => tags.push({k, sub, label});
                if (f.filterTitle) push('filterTitle', null, 'Titel: ' + f.filterTitle);
                if (f.filterIngredients) push('filterIngredients', null, 'Zutaten: ' + f.filterIngredients);
                const countryLabels = { 'ar':'Argentinien','au':'Australien','be':'Belgien','ba':'Bosnien-Herzegowina','br':'Brasilien','bg':'Bulgarien','cl':'Chile','cn':'China','de':'Deutschland','dk':'Dänemark','fi':'Finnland','fr':'Frankreich','gr':'Griechenland','gb':'Großbritannien','in':'Indien','id':'Indonesien','ie':'Irland','il':'Israel','it':'Italien','jp':'Japan','ca':'Kanada','hr':'Kroatien','lv':'Lettland','lt':'Litauen','ma':'Marokko','mx':'Mexiko','mn':'Mongolei','nz':'Neuseeland','nl':'Niederlande','no':'Norwegen','pe':'Peru','ph':'Philippinen','pt':'Portugal','ro':'Rumänien','ru':'Russland','se':'Schweden','ch':'Schweiz','rs':'Serbien','sc':'Seychellen','sg':'Singapur','sk':'Slowakei','si':'Slowenien','es':'Spanien','th':'Thailand','cz':'Tschechische Republik','tn':'Tunesien','tr':'Türkei','us':'USA','ua':'Ukraine','hu':'Ungarn','vn':'Vietnam','cy':'Zypern','at':'Österreich' };
                if (Array.isArray(f.filterCountry)) {
                    f.filterCountry.forEach(c => { if (c) push('filterCountry', c, 'Land: ' + (countryLabels[c] || c)); });
                }
                const expandDict = (obj, k, map, prefix) => {
                    if (!obj) return;
                    // Handle both array format ['dessert'] and object format {dessert: true}
                    if (Array.isArray(obj)) {
                        obj.forEach(key => { if (key) push(k, key, prefix + ': ' + (map[key] || key)); });
                    } else if (typeof obj === 'object') {
                        Object.keys(obj).forEach(key => { if (obj[key]) push(k, key, prefix + ': ' + (map[key] || key)); });
                    }
                };
                expandDict(f.filterAllergen, 'filterAllergen', { 'peanuts':'Erdnüsse','fish':'Fisch','gluten':'Glutenhaltiges Getreide','egg':'Hühnerei','crustaceans':'Krebstiere','lupin':'Lupinen','milk':'Milch','nuts':'Schalenfrüchte','sulphure':'Schwefeldioxid und Sulfit','celery':'Sellerie','mustard':'Senf','sesame':'Sesamsamen','soybeans':'Soja','molluscs':'Weichtiere' }, 'Allergen');
                expandDict(f.filterCategory, 'filterCategory', { 'side_dish':'Beilage','fingerfood':'Fingerfood','fish':'Fisch & Meeresfrüchte','meat':'Fleisch','vegetables':'Gemüse','drink':'Getränk','cake':'Kuchen','salad':'Salat','soup':'Suppe' }, 'Kategorie');
                expandDict(f.filterCourse, 'filterCourse', { 'starter':'Vorspeise','main_course':'Hauptgericht','dessert':'Dessert' }, 'Gang');
                expandDict(f.filterDiets, 'filterDiets', { 'egg-free':'Eifrei','gluten-free':'Glutenfrei','laktose-free':'Laktosefrei','fish-free':'Ohne Fisch','meat-free':'Ohne Fleisch','soy-free':'Sojafrei','vegan':'Vegan','vegetarian':'Vegetarisch','wheat-free':'Weizenfrei','alcohol-free':'Ohne Alkohol','histamine-low':'Histaminarm' }, 'Ernährung');
                expandDict(f.filterDifficulty, 'filterDifficulty', { 'easy':'einfach','medium':'mittel','difficult':'schwierig' }, 'Schwierigkeit');
                expandDict(f.filterMaxTime, 'filterMaxTime', { 'lte_30':'Bis 30 Minuten','lte_60':'Bis 60 Minuten','lte_120':'Bis 2 Stunden','gte_120':'Mehr als 2 Stunden' }, 'Zeit');
                const subsLabels = { 'fructose':'Fruktose', 'vitamin B1(thiamin)':'Vitamin B1 (thiamin)', 'carbohydrates':'Kohlenhydrate', 'protein':'Protein' };
                if (f.filterSubstances && typeof f.filterSubstances==='object') {
                    Object.keys(f.filterSubstances).forEach(sk => {
                        const s = f.filterSubstances[sk];
                        if (s && (s.enabled || (s.op && s.op!==''))) push('filterSubstances', sk, 'Substanzen: ' + (subsLabels[sk] || sk));
                    });
                }
                return tags;
            },
            hasActiveFilters() {
                const f = this.filters || {};
                if (f.filterTitle || f.filterIngredients) return true;
                if (Array.isArray(f.filterCountry) && f.filterCountry.length) return true;
                const anyTrue = (obj) => obj && typeof obj === 'object' && Object.keys(obj).some(k => !!obj[k]);
                if (anyTrue(f.filterAllergen) || anyTrue(f.filterCategory) || anyTrue(f.filterCourse) || anyTrue(f.filterDiets) || anyTrue(f.filterDifficulty) || anyTrue(f.filterMaxTime)) return true;
                if (f.filterSubstances && Object.keys(f.filterSubstances).length) return true;
                return false;
            },
            removeTag(tag) {
                if (!tag || !tag.k) return;
                const k = tag.k; const sub = tag.sub;
                const form = this.$refs ? this.$refs.filterForm : null;
                if (k === 'filterTitle') {
                    this.filters.filterTitle = '';
                    const el = form && form.querySelector('[name=filterTitle]'); if (el) el.value = '';
                    return;
                }
                if (k === 'filterIngredients') {
                    this.filters.filterIngredients = '';
                    const el = form && form.querySelector('[name=filterIngredients]'); if (el) el.value = '';
                    return;
                }
                if (k === 'filterCountry') {
                    if (Array.isArray(this.filters.filterCountry)) {
                        this.filters.filterCountry = this.filters.filterCountry.filter(v => v !== sub);
                    }
                    if (form) {
                        const sel = form.querySelector('select[name="filterCountry[]"]');
                        if (sel) { for (const o of sel.options) { if (o.value === sub) o.selected = false; } }
                    }
                    return;
                }
                if (k === 'filterSubstances') {
                    if (this.filters.filterSubstances && this.filters.filterSubstances[sub]) {
                        delete this.filters.filterSubstances[sub];
                    }
                    if (form) {
                        const pref = `filterSubstances[${sub}]`;
                        const nodes = form.querySelectorAll(`[name^="${pref}"]`);
                        nodes && nodes.forEach(i => { if (i.type === 'checkbox') i.checked = false; else i.value = ''; });
                        const cb = form.querySelector(`input[name="filterSubstances[${sub}]"]`); if (cb) cb.checked = false;
                    }
                    return;
                }
                if (this.filters[k] && typeof this.filters[k] === 'object') {
                    if (Object.prototype.hasOwnProperty.call(this.filters[k], sub)) {
                        this.filters[k][sub] = false;
                    }
                    if (form) {
                        const cb = form.querySelector(`input[name="${k}[${sub}]"]`);
                        if (cb) cb.checked = false;
                    }
                }
            },
            clearAllFilters() {
                this.filters = { filterTitle:'', filterIngredients:'', filterAllergen:{}, filterCategory:{}, filterCountry:[], filterCourse:{}, filterDiets:{}, filterDifficulty:{}, filterMaxTime:{}, filterSubstances:{} };
                const form = this.$refs ? this.$refs.filterForm : null;
                if (form) {
                    form.querySelectorAll('input[type=text]').forEach(i => i.value = '');
                    form.querySelectorAll('input[type=checkbox]').forEach(i => i.checked = false);
                    form.querySelectorAll('input[type=number]').forEach(i => i.value = '');
                    form.querySelectorAll('select').forEach(s => { if (s.multiple) { for (const o of s.options) o.selected = false; } else { s.selectedIndex = 0; } });
                }
            },
            extractFiltersFromForm(form) {
                const fd = new FormData(form);
                const getBoolDict = (prefix) => {
                    const out = {};
                    for (const [k, v] of fd.entries()) {
                        if (k.startsWith(prefix + '[')) {
                            const key = k.substring(prefix.length + 1, k.length - 1);
                            out[key] = true;
                        }
                    }
                    return out;
                };
                const getArray = (name) => fd.getAll(name) || [];
                this.filters.filterTitle = (fd.get('filterTitle') || '').toString().trim();
                this.filters.filterIngredients = (fd.get('filterIngredients') || '').toString().trim();
                this.filters.filterAllergen = getBoolDict('filterAllergen');
                this.filters.filterCategory = getBoolDict('filterCategory');
                this.filters.filterCourse = getBoolDict('filterCourse');
                this.filters.filterDiets = getBoolDict('filterDiets');
                this.filters.filterDifficulty = getBoolDict('filterDifficulty');
                this.filters.filterMaxTime = getBoolDict('filterMaxTime');
                this.filters.filterCountry = getArray('filterCountry[]');
                const subs = {};
                for (const [k, v] of fd.entries()) {
                    if (k.startsWith('filterSubstances[')) {
                        const inner = k.slice('filterSubstances['.length, -1);
                        const parts = inner.split('][');
                        const key = parts[0]; const subKey = parts[1] || null;
                        if (!subs[key]) subs[key] = { enabled: false, op: '', val1: '', val2: '' };
                        if (subKey === null) { subs[key].enabled = true; }
                        else if (subKey === 'op') { subs[key].op = v; }
                        else if (subKey === 'val1') { subs[key].val1 = v; }
                        else if (subKey === 'val2') { subs[key].val2 = v; }
                    }
                }
                this.filters.filterSubstances = subs;
                ['filterAllergen','filterCategory','filterCourse','filterDiets','filterDifficulty','filterMaxTime'].forEach(k => {
                    if (!this.filters[k]) this.filters[k] = {};
                });
                this.formChanged = false;
            },
            async saveFilters() {
                if (!this.bookId || !this.patientId) {
                    console.error('saveFilters: missing bookId or patientId', { bookId: this.bookId, patientId: this.patientId });
                    return;
                }
                const body = {
                    filters: this.filters || {},
                    availTotal: this.availTotal || 0
                };
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
                if (!this.bookId || !this.patientId) {
                    console.error('recreateBook: missing bookId or patientId', { bookId: this.bookId, patientId: this.patientId });
                    return;
                }

                // Show immediate notification
                new FilamentNotification().title('Buch wird neu generiert').body('Bitte warten...').info().send();

                const body = { filters: this.filters || {}, updateBookWithFilters: true };
                try {
                    const response = await fetch(`/admin/patients/${encodeURIComponent(this.patientId)}/filters`, {
                        method: 'POST', headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': this.csrf() }, body: JSON.stringify(body)
                    });
                    if (!response.ok) {
                        throw new Error('Failed to start book recreation');
                    }

                    // Start polling for book status
                    this.pollBookStatus();
                } catch (e) {
                    console.error('recreateBook error:', e);
                    new FilamentNotification().title('Fehler beim Starten').danger().send();
                }
            },
            pollBookStatus() {
                let pollCount = 0;
                const maxPolls = 60; // 60 * 3 seconds = 3 minutes max

                const checkStatus = async () => {
                    pollCount++;

                    try {
                        const response = await fetch(`/books/${this.bookId}/status`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (!response.ok) {
                            throw new Error('Failed to check book status');
                        }

                        const data = await response.json();
                        const status = data.status;

                        // Check if book is ready
                        if (['Warten auf Versand', 'Versendet'].includes(status)) {
                            clearInterval(pollInterval);
                            new FilamentNotification()
                                .title('Buch erfolgreich erstellt')
                                .body('Das Buch wurde mit den aktuellen Filtern neu generiert.')
                                .success()
                                .send();

                            // Reload the page to show updated recipes
                            setTimeout(() => window.location.reload(), 2000);
                            return;
                        }

                        // Check if we've exceeded max polls
                        if (pollCount >= maxPolls) {
                            clearInterval(pollInterval);
                            new FilamentNotification()
                                .title('Zeitüberschreitung')
                                .body('Die Bucherstellung dauert länger als erwartet. Bitte laden Sie die Seite neu.')
                                .warning()
                                .send();
                        }
                    } catch (e) {
                        console.error('Poll error:', e);
                        clearInterval(pollInterval);
                        new FilamentNotification()
                            .title('Fehler beim Überprüfen')
                            .body('Status konnte nicht abgerufen werden.')
                            .danger()
                            .send();
                    }
                };

                // Poll every 3 seconds
                const pollInterval = setInterval(checkStatus, 3000);
                // Check immediately
                checkStatus();
            },
            // ui actions
            showModal: false,
            modalRecipeId: null,
            modalRecipe: null,
            openRecipe(r) { 
                const id = this.idOf(r); 
                if (!id) return; 
                this.modalRecipeId = id;
                this.loadRecipeForModal(id);
            },
            async loadRecipeForModal(id) {
                try {
                    const response = await fetch(`/recipe/view/${id}`);
                    if (!response.ok) throw new Error('Failed to load recipe');
                    const html = await response.text();
                    this.modalRecipe = html;
                    this.showModal = true;
                } catch (error) {
                    console.error('Error loading recipe:', error);
                    alert('Fehler beim Laden des Rezepts');
                }
            },
            closeModal() {
                this.showModal = false;
                this.modalRecipeId = null;
                this.modalRecipe = null;
            },
            isFavorite(id) { return !!this.favoriteRecipes.find(x => this.idOf(x)===id); },
            addToBook(r) {
                const id = this.idOf(r); if (!id || !this.bookId) return;
                if (!this.bookRecipes.find(x => this.idOf(x)===id)) this.bookRecipes.unshift(r);
                this.favoriteRecipes = this.favoriteRecipes.filter(x => this.idOf(x)!==id);
                this.availableRecipes = this.availableRecipes.filter(x => this.idOf(x)!==id);
                this.computeBookCourseCounts();
                fetch(`/books/${this.bookId}/recipes/${id}`, {
                    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() }
                }).then(resp => { if (!resp.ok) throw new Error('failed') })
                  .catch(() => {
                      this.bookRecipes = this.bookRecipes.filter(x => this.idOf(x)!==id);
                      this.availableRecipes.unshift(r);
                  })
                  .finally(() => { this.loadAvailPage(this.availPage); });
            },
            removeFromBook(r) {
                const id = this.idOf(r); if (!id || !this.bookId) return;
                this.bookRecipes = this.bookRecipes.filter(x => this.idOf(x)!==id);
                if (!this.availableRecipes.find(x => this.idOf(x)===id)) this.availableRecipes.unshift(r);
                this.computeBookCourseCounts();
                fetch(`/books/${this.bookId}/recipes/${id}`, {
                    method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrf() }
                }).then(resp => { if (!resp.ok) throw new Error('failed') })
                  .catch(() => {
                      this.availableRecipes = this.availableRecipes.filter(x => this.idOf(x)!==id);
                      this.bookRecipes.unshift(r);
                  })
                  .finally(() => { this.loadAvailPage(this.availPage); });
            },
            addToFavorites(r) {
                const id = this.idOf(r); if (!id) return;
                // optimistic UI: add to favorites list if viewing and not already present
                if (!this.favoriteRecipes.find(x => this.idOf(x)===id)) {
                    this.favoriteRecipes.unshift(r);
                    // keep current page length <= perPage for a stable pager view
                    if (this.favoriteRecipes.length > this.perPage) this.favoriteRecipes.pop();
                }
                this.availableRecipes = this.availableRecipes.filter(x => this.idOf(x)!==id);
                // IMPORTANT: do NOT remove from book when toggling favorite in book
                this.favTotal = Math.max(0, (this.favTotal||0) + 1);
                fetch(`/favorites/${id}`, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() } })
                  .catch(()=>{})
                  .finally(() => { this.loadFavPage(this.favPage); this.loadAvailPage(this.availPage); });
            },
            removeFromFavorites(r) {
                const id = this.idOf(r); if (!id) return;
                this.favoriteRecipes = this.favoriteRecipes.filter(x => this.idOf(x)!==id);
                this.favTotal = Math.max(0, (this.favTotal||0) - 1);
                // if current page becomes empty after removal, try to load previous page
                if (this.favoriteRecipes.length === 0 && this.favPage > 1) {
                    this.loadFavPage(this.favPage - 1);
                }
                fetch(`/favorites/${id}`, { method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() } })
                  .catch(()=>{})
                  .finally(() => { if (this.favoriteRecipes.length) this.loadFavPage(this.favPage); this.loadAvailPage(this.availPage); });
            },
        }
    }
    </script>

    <!-- Recipe Modal -->
    <div x-show="showModal" 
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" 
         @click="closeModal()"
         style="display: none;">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg w-full max-w-6xl relative" 
             @click.stop 
             style="max-height:90vh; overflow-y:auto;">
            <button class="absolute top-4 right-4 z-10 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 text-3xl font-bold" 
                    @click="closeModal()">&times;</button>
            <div class="p-6" x-html="modalRecipe"></div>
        </div>
    </div>
</x-filament-panels::page>
