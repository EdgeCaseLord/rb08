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
        return {
            // state
            bookRecipes: initial.bookRecipes || [],
            favoriteRecipes: initial.favoriteRecipes || [],
            availableRecipes: initial.availableRecipes || [],
            bookId: initial.bookId,
            recipeLimits: initial.recipeLimits || { starter: 5, main_course: 5, dessert: 5 },
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
                if (arr.length === 1 && typeof arr[0] === 'object' && !Array.isArray(arr[0])) {
                    const dict = fromDictBooleans(arr[0]);
                    if (dict) out.push(...dict.split(', '));
                }
                for (const item of arr) {
                    if (typeof item === 'string') { const s=item.trim(); if (s && s.toLowerCase()!=='value' && !isTrueString(s) && !isPlaceholder(s)) out.push(s); continue; }
                    if (typeof item === 'boolean') { continue; }
                    if (Array.isArray(item)) { continue; }
                    if (item && typeof item === 'object') {
                        // Special case: { allergen: 'X', value: true }
                        if ((Object.prototype.hasOwnProperty.call(item,'allergen') || Object.prototype.hasOwnProperty.call(item,'diet')) && Object.prototype.hasOwnProperty.call(item,'value')) {
                            const k = (item.allergen || item.diet || '').toString().trim();
                            if (k && item.value === true) { out.push(k); continue; }
                        }
                        const named = takeName(item);
                        if (named && named.toLowerCase()!=='value' && !isTrueString(named) && !isPlaceholder(named)) { out.push(named); continue; }
                        const dict = fromDictBooleans(item);
                        if (dict) out.push(...dict.split(', '));
                    }
                }
                const uniq = Array.from(new Set(out.filter(Boolean)));
                if (!uniq.length) return 'Keine';
                const MAX = 12;
                return uniq.length > MAX ? `${uniq.slice(0, MAX).join(', ')} …` : uniq.join(', ');
            },
            bookCourseCounts: { starter: 0, main_course: 0, dessert: 0 },
            computeBookCourseCounts() {
                const counts = { starter: 0, main_course: 0, dessert: 0 };
                (this.bookRecipes||[]).forEach(r => {
                    const cats = Array.isArray(r.category) ? r.category : [];
                    const map = (name) => {
                        const n = (typeof name === 'string') ? name.toLowerCase() : (name?.name||name?.label||'').toLowerCase();
                        if (n.includes('vorspeise')) return 'starter';
                        if (n.includes('dessert')) return 'dessert';
                        if (n.includes('haupt')) return 'main_course';
                        if (n.includes('fisch')) return 'main_course';
                        if (n.includes('fleisch')) return 'main_course';
                        return null;
                    };
                    for (const c of cats) { const key = map(c); if (key) { counts[key]++; break; } }
                });
                this.bookCourseCounts = counts;
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
                    const resp = await fetch(`/available.json?page=${page}&perPage=${this.perPageAvail}&_=${Date.now()}`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' });
                    if (resp.ok) {
                        const data = await resp.json();
                        this.availableRecipes = [...(data.items || [])];
                        this.availTotal = data.total || 0;
                        this.availPage = data.page || page;
                    }
                } finally { this.isLoadingAvail = false; }
            },
            updateAvailPerPage() {
                const isSmall = window.matchMedia('(max-width: 640px)').matches;
                const newPer = isSmall ? 3 : 6;
                if (newPer !== this.perPageAvail) {
                    this.perPageAvail = newPer;
                    this.loadAvailPage(this.availPage);
                }
            },
            // ui actions
            openRecipe(r) { const id = this.idOf(r); if (!id) return; window.open(`/recipes/${id}`, '_blank'); },
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
</x-filament-panels::page>
