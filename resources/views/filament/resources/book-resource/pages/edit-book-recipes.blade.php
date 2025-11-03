<div x-data="recipeManager(@js([
    'bookRecipes' => [],
    'favoriteRecipes' => [],
    'availableRecipes' => [],
    'bookRecipeCounts' => $bookRecipeCounts ?? ['starter' => 0, 'main_course' => 0, 'dessert' => 0],
    'recipeLimits' => $recipeLimits ?? ['starter' => 5, 'main_course' => 5, 'dessert' => 5],
    'bookId' => $bookId ?? null
]))" x-init="init()" wire:ignore>

    <!-- Book Recipes Section -->
    <x-filament::section collapsible="true" class="mb-6">
        <x-slot name="heading">
            Rezepte im Buch (<span x-text="bookRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="(recipe, i) in bookRecipes" :key="idOf(recipe) ? 'book-'+idOf(recipe) : 'book-'+i">
                <!-- Recipe card -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative mb-4">
                    <!-- Image -->
                    <div class="relative aspect-w-16 aspect-h-9">
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
                        <!-- Category tags overlay (Book) -->
                        <div class="absolute bottom-2 left-2 flex flex-wrap gap-1" x-show="categories(recipe.category).length > 0">
                            <template x-for="cat in categories(recipe.category)" :key="cat">
                                <span class="px-2 py-0.5 rounded-sm bg-orange-500 text-white text-xs" x-text="cat"></span>
                            </template>
                        </div>
                    </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Allergene:</strong> <span x-text="labels(recipe.allergens)"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="labels(recipe.diets)"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" class="px-2 py-1 rounded text-blue-600 hover:text-blue-800" @click.stop.prevent="openRecipe(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded"
                                    :class="isFavorite(idOf(recipe))
                                        ? 'text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300'
                                        : 'text-gray-600 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400'"
                                    @click.stop.prevent="isFavorite(idOf(recipe)) ? removeFromFavorites(recipe) : addToFavorites(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 18.656l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-red-600 hover:text-red-800" @click.stop.prevent="removeFromBook(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 inline">
                                        <path d="M6 7h12"/>
                                        <path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/>
                                        <path d="M10 11v6"/>
                                        <path d="M14 11v6"/>
                                        <path d="M19 7l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 7"/>
                                    </svg>
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
        <div class="mt-4 text-sm text-gray-600">
            Vorspeisen: <span x-text="bookCourseCounts.starter"></span>/<span x-text="recipeLimits.starter"></span> |
            Hauptgerichte: <span x-text="bookCourseCounts.main_course"></span>/<span x-text="recipeLimits.main_course"></span> |
            Desserts: <span x-text="bookCourseCounts.dessert"></span>/<span x-text="recipeLimits.dessert"></span>
        </div>
    </x-filament::section>

    <!-- Favorites Section -->
    <x-filament::section collapsible="true" class="mb-6">
        <x-slot name="heading">
            Favoriten (<span x-text="favoriteRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="(recipe, i) in favoriteRecipes" :key="idOf(recipe) ? 'fav-'+idOf(recipe) : 'fav-'+i">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="relative aspect-w-16 aspect-h-9">
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
                            <!-- Category tags overlay -->
                            <div class="absolute bottom-2 left-2 flex flex-wrap gap-1" x-show="categories(recipe.category).length > 0">
                                <template x-for="cat in categories(recipe.category)" :key="cat">
                                    <span class="px-2 py-0.5 rounded-sm bg-orange-500 text-white text-xs" x-text="cat"></span>
                                </template>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Allergene:</strong> <span x-text="labels(recipe.allergens)"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="labels(recipe.diets)"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" class="px-2 py-1 rounded text-blue-600 hover:text-blue-800" @click.prevent="openRecipe(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-red-600 hover:text-red-800" @click.stop.prevent="if(confirm('Wirklich aus Favoriten entfernen?')) { removeFromFavorites(recipe) }">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 18.656l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-green-600 hover:text-green-800" @click.stop.prevent="addToBook(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div class="mt-2 flex items-center justify-between">
            <button class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-40" :disabled="favPage<=1" @click="loadFavPage(favPage-1)">Zurück</button>
            <div class="text-xs text-gray-500" x-text="pageLabelTotal(favPage, perPage, favTotal)"></div>
            <button class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-40" :disabled="favPage>=pagesTotal(perPage, favTotal)" @click="loadFavPage(favPage+1)">Weiter</button>
        </div>
    </x-filament::section>

    <!-- Available Recipes Section -->
    <x-filament::section collapsible="true" class="mb-6">
        <x-slot name="heading">
            Verfügbare Rezepte (<span x-text="availableRecipes.length"></span>)
        </x-slot>
        <div class="mb-3 border border-gray-200 dark:border-gray-700 rounded-lg">
            <div x-data="{ openFilters: false }" class="bg-gray-50 dark:bg-gray-900 rounded-lg">
                <button type="button" class="w-full flex items-center justify-between px-3 py-2 text-sm" @click="openFilters = !openFilters">
                    <span class="font-medium">Filter</span>
                    <svg :class="{'rotate-180': openFilters}" class="h-4 w-4 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                </button>
                <div x-show="openFilters" x-transition class="px-3 pb-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <input type="text" class="filament-input rounded-lg" placeholder="Titel" x-model.debounce.400ms="filters.filterTitle">
                        <input type="text" class="filament-input rounded-lg" placeholder="Zutaten (Bsp.: paprika, nudeln -aprikosen)" x-model.debounce.500ms="filters.filterIngredients">
                    </div>
                    <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <div class="text-xs font-semibold mb-1">Allergene</div>
                            <div class="flex flex-wrap gap-2">
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.peanuts"> <span>Erdnüsse</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.fish"> <span>Fisch</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.gluten"> <span>Glutenhaltiges Getreide</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.egg"> <span>Hühnerei</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.crustaceans"> <span>Krebstiere</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.lupin"> <span>Lupinen</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.milk"> <span>Milch</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.nuts"> <span>Schalenfrüchte</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.sulphure"> <span>Schwefeldioxid und Sulfit</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.celery"> <span>Sellerie</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.mustard"> <span>Senf</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.sesame"> <span>Sesamsamen</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.soybeans"> <span>Soja</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterAllergen.molluscs"> <span>Weichtiere</span></label>
                            </div>
                        </div>
                        <div>
                            <div class="text-xs font-semibold mb-1">Ernährungsweise</div>
                            <div class="flex flex-wrap gap-2">
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets.eifrei"> <span>Eifrei</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets.glutenfrei"> <span>Glutenfrei</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets.laktosefrei"> <span>Laktosefrei</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets['fish-free']"> <span>Ohne Fisch</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets['meat-free']"> <span>Ohne Fleisch</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets['soy-free']"> <span>Sojafrei</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets.vegan"> <span>Vegan</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets.vegetarian"> <span>Vegetarisch</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets['wheat-free']"> <span>Weizenfrei</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets['alcohol-free']"> <span>Ohne Alkohol</span></label>
                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" x-model="filters.filterDiets['histamine-low']"> <span>Histaminarm</span></label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-end">
                        <button type="button" class="px-3 py-1 bg-primary-600 text-white rounded hover:bg-primary-700 text-sm" @click="applyFilters()">Filter anwenden</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4" wire:ignore>
            <template x-for="(recipe, i) in availableRecipes" :key="idOf(recipe) ? 'avail-'+idOf(recipe) : 'avail-'+i">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="relative aspect-w-16 aspect-h-9">
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
                            <!-- Category tags overlay (Available) -->
                            <div class="absolute bottom-2 left-2 flex flex-wrap gap-1" x-show="categories(recipe.category).length > 0">
                                <template x-for="cat in categories(recipe.category)" :key="cat">
                                    <span class="px-2 py-0.5 rounded-sm bg-orange-500 text-white text-xs" x-text="cat"></span>
                                </template>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-4">
                            <h3 x-text="recipe.title" class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-2"></h3>

                            <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                <p><strong>Allergene:</strong> <span x-text="labels(recipe.allergens)"></span></p>
                                <p><strong>Ernährungsweise:</strong> <span x-text="labels(recipe.diets)"></span></p>
                            </div>

                            <!-- Actions -->
                            <div class="mt-4 flex justify-end space-x-2">
                                <button type="button" class="px-2 py-1 rounded text-blue-600 hover:text-blue-800" @click.prevent="openRecipe(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-gray-600 hover:text-red-500 dark:text-gray-400 dark:hover:text-red-400" @click.stop.prevent="addToFavorites(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" viewBox="0 0 20 20" fill="currentColor"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 18.656l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                                </button>
                                <button type="button" class="px-2 py-1 rounded text-green-600 hover:text-green-800" @click.prevent="addToBook(recipe)">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        <div class="mt-2 flex items-center justify-between">
            <button class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-40" :disabled="availPage<=1" @click="loadAvailPage(availPage-1)">Zurück</button>
            <div class="text-xs text-gray-500" x-text="pageLabelTotal(availPage, perPageAvail, availTotal)"></div>
            <button class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded disabled:opacity-40" :disabled="availPage>=pagesTotal(perPageAvail, availTotal)" @click="loadAvailPage(availPage+1)">Weiter</button>
        </div>
    </x-filament::section>
    </div>
