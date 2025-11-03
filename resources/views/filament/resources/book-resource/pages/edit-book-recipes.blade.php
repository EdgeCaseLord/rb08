@php
    $simplify = function($items) {
        $arr = is_iterable($items) ? (is_array($items) ? $items : (method_exists($items,'all') ? $items->all() : (array)$items)) : [];
        $out = [];
        foreach ($arr as $r) {
            // normalize to array
            if (is_object($r)) { $r = (array)$r; }
            // id fields
            $id = $r['id_recipe'] ?? $r['id_external'] ?? $r['id'] ?? null;
            $title = $r['title'] ?? '';
            // decode fields possibly stored as JSON strings
            $media = isset($r['media']) ? (is_string($r['media']) ? (json_decode($r['media'], true) ?: []) : (is_array($r['media']) ? $r['media'] : [])) : [];
            $images = isset($r['images']) ? (is_string($r['images']) ? (json_decode($r['images'], true) ?: []) : (is_array($r['images']) ? $r['images'] : [])) : [];
            $category = isset($r['category']) ? (is_string($r['category']) ? (json_decode($r['category'], true) ?: []) : (is_array($r['category']) ? $r['category'] : [])) : [];
            $diets = isset($r['diets']) ? (is_string($r['diets']) ? (json_decode($r['diets'], true) ?: []) : (is_array($r['diets']) ? $r['diets'] : [])) : [];
            $allergens = isset($r['allergens']) ? (is_string($r['allergens']) ? (json_decode($r['allergens'], true) ?: []) : (is_array($r['allergens']) ? $r['allergens'] : [])) : [];
            // choose smallest image
            $thumb = null;
            if (!empty($media['search'])) {
                $thumb = is_array($media['search']) ? ($media['search'][0] ?? null) : $media['search'];
            } elseif (!empty($images)) {
                $thumb = $images[0] ?? null;
            }
            $out[] = [
                'id_recipe' => $r['id_recipe'] ?? null,
                'id_external' => $r['id_external'] ?? null,
                'id' => $r['id'] ?? null,
                'title' => $title,
                'media' => [ 'search' => $thumb ? [$thumb] : [] ],
                'images' => $thumb ? [$thumb] : [],
                'category' => array_values(array_filter($category)),
                'diets' => array_values(array_filter(is_array($diets)?$diets:[])),
                'allergens' => array_values(array_filter(is_array($allergens)?$allergens:[])),
            ];
        }
        return $out;
    };
    $bookRecipesSlim = $simplify($bookRecipes ?? []);
    $favoriteRecipesSlim = $simplify($favoriteRecipes ?? []);
    $availableRecipesSlim = $simplify($availableRecipes ?? []);
@endphp
<div x-data="recipeManager(@js([
    'bookRecipes' => $bookRecipesSlim,
    'favoriteRecipes' => $favoriteRecipesSlim,
    'availableRecipes' => $availableRecipesSlim,
    'bookRecipeCounts' => $bookRecipeCounts ?? ['starter' => 0, 'main_course' => 0, 'dessert' => 0],
    'recipeLimits' => $recipeLimits ?? ['starter' => 5, 'main_course' => 5, 'dessert' => 5],
    'bookId' => $bookId ?? null
]))" x-init="init()">

    <!-- Book Recipes Section -->
    <x-filament::section>
        <x-slot name="heading">
            Rezepte im Buch (<span x-text="bookRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="recipe in paged(bookRecipes, bookPage, perPage)" :key="idOf(recipe)">
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
        <div class="mt-2 flex items-center justify-between">
            <button class="px-3 py-1 text-sm bg-gray-200 rounded disabled:opacity-40" :disabled="bookPage<=1" @click="bookPage--">Zurück</button>
            <div class="text-xs text-gray-500" x-text="pageLabel(bookRecipes, bookPage, perPage)"></div>
            <button class="px-3 py-1 text-sm bg-gray-200 rounded disabled:opacity-40" :disabled="bookPage>=pages(bookRecipes, perPage)" @click="bookPage++">Weiter</button>
        </div>
                            </template>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Kategorie:</strong> <span x-text="recipe.category && recipe.category.length > 0 ? recipe.category.join(', ') : 'Keine'"></span></p>
                                <p><strong>Allergene:</strong> <span x-text="recipe.allergens && recipe.allergens.length > 0 ? recipe.allergens.join(', ') : 'Keine'"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="recipe.diets && recipe.diets.length > 0 ? recipe.diets.join(', ') : 'Keine'"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <!-- View Recipe -->
                                <x-filament::icon-button
                                    icon="heroicon-o-eye"
                                    color="primary"
                                    tooltip="Rezept ansehen"
                                    x-on:click.prevent="openRecipe(recipe)"
                                />

                                <!-- Remove from Book -->
                                <x-filament::icon-button
                                    icon="heroicon-o-trash"
                                    color="danger"
                                    tooltip="Aus Buch entfernen"
                                    x-on:click.prevent="removeFromBook(recipe)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
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
            <template x-for="recipe in paged(favoriteRecipes, favPage, perPage)" :key="idOf(recipe)">
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
                                <p><strong>Kategorie:</strong> <span x-text="recipe.category && recipe.category.length > 0 ? recipe.category.join(', ') : 'Keine'"></span></p>
                                <p><strong>Allergene:</strong> <span x-text="recipe.allergens && recipe.allergens.length > 0 ? recipe.allergens.join(', ') : 'Keine'"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="recipe.diets && recipe.diets.length > 0 ? recipe.diets.join(', ') : 'Keine'"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <!-- View Recipe -->
                                <x-filament::icon-button
                                    icon="heroicon-o-eye"
                                    color="primary"
                                    tooltip="Rezept ansehen"
                                    x-on:click.prevent="openRecipe(recipe)"
                                />

                                <!-- Add to Book -->
                                <x-filament::icon-button
                                    icon="heroicon-o-plus"
                                    color="success"
                                    tooltip="Zum Buch hinzufügen"
                                    x-on:click.prevent="addToBook(recipe)"
                                />

                                <!-- Remove from Favorites -->
                                <x-filament::icon-button
                                    icon="heroicon-s-heart"
                                    color="danger"
                                    tooltip="Aus Favoriten entfernen"
                                    x-on:click.prevent="if(confirm('Wirklich aus Favoriten entfernen?')) { removeFromFavorites(recipe) }"
                                />
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
            <template x-for="recipe in paged(availableRecipes, availPage, perPageAvail)" :key="idOf(recipe)">
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
                                <p><strong>Kategorie:</strong> <span x-text="recipe.category && recipe.category.length > 0 ? recipe.category.join(', ') : 'Keine'"></span></p>
                                <p><strong>Allergene:</strong> <span x-text="recipe.allergens && recipe.allergens.length > 0 ? recipe.allergens.join(', ') : 'Keine'"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="recipe.diets && recipe.diets.length > 0 ? recipe.diets.join(', ') : 'Keine'"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <!-- View Recipe -->
                                <x-filament::icon-button
                                    icon="heroicon-o-eye"
                                    color="primary"
                                    tooltip="Rezept ansehen"
                                    x-on:click.prevent="openRecipe(recipe)"
                                />

                                <!-- Add to Book -->
                                <x-filament::icon-button
                                    icon="heroicon-o-plus"
                                    color="success"
                                    tooltip="Zum Buch hinzufügen"
                                    x-on:click.prevent="addToBook(recipe)"
                                />

                                <!-- Add to Favorites -->
                                <x-filament::icon-button
                                    icon="heroicon-o-heart"
                                    color="gray"
                                    tooltip="Zu Favoriten hinzufügen"
                                    x-on:click.prevent="addToFavorites(recipe)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>
</div>

<script>
function recipeManager(initial) {
    return {
        // state
        bookRecipes: initial.bookRecipes || [],
        favoriteRecipes: initial.favoriteRecipes || [],
        availableRecipes: initial.availableRecipes || [],
        bookId: initial.bookId,
        // pagination
        perPage: 24,
        perPageAvail: 24,
        bookPage: 1,
        favPage: 1,
        availPage: 1,
        init() {},
        // helpers
        idOf(r) { return r?.id_recipe || r?.id_external || r?.id || null },
        pages(list, per) { const n = Math.max(1, Math.ceil(((list||[]).length) / per)); return n },
        paged(list, page, per) { const start = (page-1)*per; return (list||[]).slice(start, start+per) },
        pageLabel(list, page, per) { const total = (list||[]).length; const p = this.pages(list, per); return `${Math.min(page,p)}/${p} · ${total} Einträge` },
        csrf() { return document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '' },
        // ui actions
        openRecipe(r) { const id = this.idOf(r); if (!id) return; window.dispatchEvent(new CustomEvent('openRecipeModal', { detail: [id] })) },
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
