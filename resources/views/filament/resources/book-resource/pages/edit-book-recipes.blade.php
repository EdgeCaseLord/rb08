<div x-data="recipeManager(@js([
    'bookRecipes' => [],
    'favoriteRecipes' => [],
    'availableRecipes' => [],
    'bookRecipeCounts' => $bookRecipeCounts ?? ['starter' => 0, 'main_course' => 0, 'dessert' => 0],
    'recipeLimits' => $recipeLimits ?? ['starter' => 5, 'main_course' => 5, 'dessert' => 5],
    'bookId' => $bookId ?? null
]))" x-init="init()" wire:ignore>

    <!-- Book Recipes Section -->
    <x-filament::section>
        <x-slot name="heading">
            Rezepte im Buch (<span x-text="bookRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="recipe in bookRecipes" :key="idOf(recipe)">
                <!-- Recipe card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative mb-4">
                    <!-- Image -->
                    <div class="aspect-w-16 aspect-h-9">
                        <template x-if="recipe.media && recipe.media.search && recipe.media.search.length > 0">
                            <img :src="recipe.media.search[0]" alt="Rezept Bild" loading="lazy" decoding="async" fetchpriority="low" width="640" height="360" class="w-full h-48 object-cover object-center">
                        </template>
                        <template x-if="(!recipe.media || !recipe.media.search || recipe.media.search.length === 0) && recipe.images && recipe.images.length > 0">
                            <img :src="recipe.images[0]" alt="Rezept Bild" loading="lazy" decoding="async" fetchpriority="low" width="640" height="360" class="w-full h-48 object-cover object-center">
                        </template>
                        <template x-if="(!recipe.media || !recipe.media.search || recipe.media.search.length === 0) && (!recipe.images || recipe.images.length === 0)">
                            <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                <span class="text-gray-500 dark:text-gray-300 text-sm">Kein Bild</span>
                            </div>
                        </template>
                    </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Kategorie:</strong> <span x-text="labels(recipe.category)"></span></p>
                                <p><strong>Allergene:</strong> <span x-text="labels(recipe.allergens)"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="labels(recipe.diets)"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" class="px-2 py-1 rounded text-blue-600 hover:text-blue-800" @click.stop.prevent="openRecipe(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded" :class="isFavorite(idOf(recipe)) ? 'text-red-600 hover:text-red-800' : 'text-gray-600 hover:text-gray-800'" @click.stop.prevent="isFavorite(idOf(recipe)) ? removeFromFavorites(recipe) : addToFavorites(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 18.656l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-red-600 hover:text-red-800" @click.stop.prevent="removeFromBook(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6 7h12M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2m-1 0l-.867 12.142A2 2 0 0113.138 21H10.86a2 2 0 01-1.995-1.858L8 7h8z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div class="mt-2 flex items-center justify-between">
            <button class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-40" :disabled="bookPage<=1" @click="loadBookPage(bookPage-1)">Zurück</button>
            <div class="text-xs text-gray-500" x-text="pageLabelTotal(bookPage, perPage, bookTotal)"></div>
            <button class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-40" :disabled="bookPage>=pagesTotal(perPage, bookTotal)" @click="loadBookPage(bookPage+1)">Weiter</button>
        </div>
        <!-- Show limits -->
        <div class="mt-4 text-sm text-gray-600">
            Vorspeisen: <span x-text="bookRecipeCounts.starter"></span>/<span x-text="recipeLimits.starter"></span> |
            Hauptgerichte: <span x-text="bookRecipeCounts.main_course"></span>/<span x-text="recipeLimits.main_course"></span> |
            Desserts: <span x-text="bookRecipeCounts.dessert"></span>/<span x-text="recipeLimits.dessert"></span>
        </div>
    </x-filament::section>

    <!-- Favorites Section -->
    <x-filament::section>
        <x-slot name="heading">
            Favoriten (<span x-text="favoriteRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="recipe in favoriteRecipes" :key="idOf(recipe)">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="aspect-w-16 aspect-h-9">
                            <template x-if="recipe.media && recipe.media.search && recipe.media.search.length > 0">
                                <img :src="recipe.media.search[0]" alt="Rezept Bild" loading="lazy" decoding="async" fetchpriority="low" width="640" height="360" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.search || recipe.media.search.length === 0) && recipe.images && recipe.images.length > 0">
                                <img :src="recipe.images[0]" alt="Rezept Bild" loading="lazy" decoding="async" fetchpriority="low" width="640" height="360" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.search || recipe.media.search.length === 0) && (!recipe.images || recipe.images.length === 0)">
                                <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                    <span class="text-gray-500 dark:text-gray-300 text-sm">Kein Bild</span>
                                </div>
                            </template>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Kategorie:</strong> <span x-text="labels(recipe.category)"></span></p>
                                <p><strong>Allergene:</strong> <span x-text="labels(recipe.allergens)"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="labels(recipe.diets)"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" class="px-2 py-1 rounded text-blue-600 hover:text-blue-800" @click.prevent="openRecipe(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-green-600 hover:text-green-800" @click.stop.prevent="addToBook(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-red-600 hover:text-red-800" @click.stop.prevent="if(confirm('Wirklich aus Favoriten entfernen?')) { removeFromFavorites(recipe) }">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 18.656l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>

    <!-- Available Recipes Section -->
    <x-filament::section>
        <x-slot name="heading">
            Verfügbare Rezepte (<span x-text="availableRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="recipe in availableRecipes" :key="idOf(recipe)">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="aspect-w-16 aspect-h-9">
                            <template x-if="recipe.media && recipe.media.search && recipe.media.search.length > 0">
                                <img :src="recipe.media.search[0]" alt="Rezept Bild" loading="lazy" decoding="async" fetchpriority="low" width="640" height="360" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.search || recipe.media.search.length === 0) && recipe.images && recipe.images.length > 0">
                                <img :src="recipe.images[0]" alt="Rezept Bild" loading="lazy" decoding="async" fetchpriority="low" width="640" height="360" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.search || recipe.media.search.length === 0) && (!recipe.images || recipe.images.length === 0)">
                                <div class="w-full h-48 bg-gray-200 dark:bg-gray-700 flex items-center justify-center">
                                    <span class="text-gray-500 dark:text-gray-300 text-sm">Kein Bild</span>
                                </div>
                            </template>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Kategorie:</strong> <span x-text="labels(recipe.category)"></span></p>
                                <p><strong>Allergene:</strong> <span x-text="labels(recipe.allergens)"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="labels(recipe.diets)"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" class="px-2 py-1 rounded text-blue-600 hover:text-blue-800" @click.prevent="openRecipe(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-green-600 hover:text-green-800" @click.prevent="addToBook(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-gray-600 hover:text-gray-800" @click.stop.prevent="addToFavorites(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 18.656l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>
@push('scripts')
<script>
function recipeManager(initial) {
    return {
        // state
        bookRecipes: initial.bookRecipes || [],
        favoriteRecipes: initial.favoriteRecipes || [],
        availableRecipes: initial.availableRecipes || [],
        bookId: initial.bookId,
        // pagination
        perPage: 6,
        perPageAvail: 6,
        bookPage: 1,
        favPage: 1,
        availPage: 1,
        // totals (from server/API)
        bookTotal: 0,
        favTotal: 0,
        availTotal: 0,
        init() {
            // set responsive available page size
            this.updateAvailPerPage();
            window.addEventListener('resize', () => this.updateAvailPerPage());
            // initial lazy loads
            this.loadBookPage(1);
            this.loadFavPage(1);
            this.loadAvailPage(1);
        },
        // helpers
        idOf(r) { return r?.id_recipe || r?.id_external || r?.id || null },
        pages(list, per) { const n = Math.max(1, Math.ceil(((list||[]).length) / per)); return n },
        paged(list, page, per) { const start = (page-1)*per; return (list||[]).slice(start, start+per) },
        pageLabel(list, page, per) { const total = (list||[]).length; const p = this.pages(list, per); return `${Math.min(page,p)}/${p} · ${total} Einträge` },
        pagesTotal(per, total) { return Math.max(1, Math.ceil((total||0)/per)); },
        pageLabelTotal(page, per, total) { const p = this.pagesTotal(per,total); return `${Math.min(page,p)}/${p} · ${total||0} Einträge`; },
        csrf() { return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
        labels(list) {
            const arr = Array.isArray(list) ? list : [];
            const names = arr.map(item => {
                if (typeof item === 'string') return item;
                if (item && typeof item === 'object') {
                    return item.name || item.label || item.title || item.value || '';
                }
                return '';
            }).filter(Boolean);
            return names.length ? names.join(', ') : 'Keine';
        },
        // data loaders (JSON, minimal fields)
        async loadBookPage(page) {
            if (!this.bookId) { this.bookRecipes = []; this.bookTotal = 0; return; }
            page = Math.max(1, page);
            const resp = await fetch(`/books/${this.bookId}/recipes.json?page=${page}&perPage=${this.perPage}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (resp.ok) {
                const data = await resp.json();
                this.bookRecipes = data.items || [];
                this.bookTotal = data.total || 0;
                this.bookPage = data.page || page;
            }
        },
        async loadFavPage(page) {
            page = Math.max(1, page);
            const resp = await fetch(`/favorites.json?page=${page}&perPage=${this.perPage}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (resp.ok) {
                const data = await resp.json();
                this.favoriteRecipes = data.items || [];
                this.favTotal = data.total || 0;
                this.favPage = data.page || page;
            }
        },
        async loadAvailPage(page) {
            page = Math.max(1, page);
            const resp = await fetch(`/available.json?page=${page}&perPage=${this.perPageAvail}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            if (resp.ok) {
                const data = await resp.json();
                this.availableRecipes = data.items || [];
                this.availTotal = data.total || 0;
                this.availPage = data.page || page;
            }
        },
        updateAvailPerPage() {
            const isSmall = window.matchMedia('(max-width: 640px)').matches;
            const newPer = isSmall ? 3 : 6;
            if (newPer !== this.perPageAvail) {
                this.perPageAvail = newPer;
                // reload current available page to reflect new perPage
                this.loadAvailPage(this.availPage);
            }
        },
        // ui actions
        openRecipe(r) { const id = this.idOf(r); if (!id) return; window.open(`/recipes/${id}`, '_blank'); },
        isFavorite(id) { return !!this.favoriteRecipes.find(x => this.idOf(x)===id); },
        addToBook(r) {
            const id = this.idOf(r); if (!id || !this.bookId) return;
            // optimistic UI
            this.bookRecipes.unshift(r);
            this.favoriteRecipes = this.favoriteRecipes.filter(x => this.idOf(x)!==id);
            this.availableRecipes = this.availableRecipes.filter(x => this.idOf(x)!==id);
            // persist via REST
            fetch(`/books/${this.bookId}/recipes/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                }
            }).then(resp => { if (!resp.ok) throw new Error('failed') })
              .catch(() => {
                  // revert UI on error
                  this.bookRecipes = this.bookRecipes.filter(x => this.idOf(x)!==id);
                  this.availableRecipes.unshift(r);
              });
        },
        removeFromBook(r) {
            const id = this.idOf(r); if (!id || !this.bookId) return;
            // optimistic UI
            this.bookRecipes = this.bookRecipes.filter(x => this.idOf(x)!==id);
            this.availableRecipes.unshift(r);
            // persist via REST
            fetch(`/books/${this.bookId}/recipes/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                }
            }).then(resp => { if (!resp.ok) throw new Error('failed') })
              .catch(() => {
                  // revert UI on error
                  this.availableRecipes = this.availableRecipes.filter(x => this.idOf(x)!==id);
                  this.bookRecipes.unshift(r);
              });
        },
        addToFavorites(r) {
            const id = this.idOf(r); if (!id) return;
            // optimistic UI
            if (!this.favoriteRecipes.find(x => this.idOf(x)===id)) this.favoriteRecipes.unshift(r);
            this.availableRecipes = this.availableRecipes.filter(x => this.idOf(x)!==id);
            // best-effort REST (optional; may 404 if not available)
            fetch(`/favorites/${id}`, {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() }
            }).catch(()=>{});
        },
        removeFromFavorites(r) {
            const id = this.idOf(r); if (!id) return;
            // optimistic UI
            this.favoriteRecipes = this.favoriteRecipes.filter(x => this.idOf(x)!==id);
            // best-effort REST
            fetch(`/favorites/${id}`, {
                method: 'DELETE', headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.csrf() }
            }).catch(()=>{});
        },
    }
}
</script>
@endpush
