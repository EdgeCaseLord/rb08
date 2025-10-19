<div x-data="recipeManager(@js([
    'bookRecipes' => $bookRecipes ?? [],
    'favoriteRecipes' => $favoriteRecipes ?? [],
    'availableRecipes' => $availableRecipes ?? [],
    'bookRecipeCounts' => $bookRecipeCounts ?? ['starter' => 0, 'main_course' => 0, 'dessert' => 0],
    'recipeLimits' => $recipeLimits ?? ['starter' => 5, 'main_course' => 5, 'dessert' => 5],
    'bookId' => $bookId ?? null
]))" x-init="console.log('Alpine.js initialized with data:', {bookRecipes: bookRecipes, favoriteRecipes: favoriteRecipes, availableRecipes: availableRecipes})">

    <!-- Book Recipes Section -->
    <x-filament::section>
        <x-slot name="heading">
            Rezepte im Buch (<span x-text="bookRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
            <template x-for="recipe in bookRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="aspect-w-16 aspect-h-9">
                            <template x-if="recipe.media && recipe.media.preview && recipe.media.preview.length > 0">
                                <img :src="recipe.media.preview[0]" alt="Rezept Bild" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="recipe.images && recipe.images.length > 0">
                                <img :src="recipe.images[0]" alt="Rezept Bild" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.preview || recipe.media.preview.length === 0) && (!recipe.images || recipe.images.length === 0)">
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
                                    x-on:click.prevent="$dispatch('openRecipeModal', [recipe.id_recipe || recipe.id_external || recipe.id])"
                                />

                                <!-- Remove from Book -->
                                <x-filament::icon-button
                                    icon="heroicon-o-trash"
                                    color="danger"
                                    tooltip="Aus Buch entfernen"
                                    x-on:click.prevent="removeFromBook(recipe.id || recipe.id_external || recipe.id_recipe)"
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

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
            <template x-for="recipe in favoriteRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="aspect-w-16 aspect-h-9">
                            <template x-if="recipe.media && recipe.media.preview && recipe.media.preview.length > 0">
                                <img :src="recipe.media.preview[0]" alt="Rezept Bild" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="recipe.images && recipe.images.length > 0">
                                <img :src="recipe.images[0]" alt="Rezept Bild" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.preview || recipe.media.preview.length === 0) && (!recipe.images || recipe.images.length === 0)">
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
                                    x-on:click.prevent="$dispatch('openRecipeModal', [recipe.id_recipe || recipe.id_external || recipe.id])"
                                />

                                <!-- Add to Book -->
                                <x-filament::icon-button
                                    icon="heroicon-o-plus"
                                    color="success"
                                    tooltip="Zum Buch hinzufügen"
                                    x-on:click.prevent="addToBook(recipe.id || recipe.id_external || recipe.id_recipe)"
                                />

                                <!-- Remove from Favorites -->
                                <x-filament::icon-button
                                    icon="heroicon-s-heart"
                                    color="danger"
                                    tooltip="Aus Favoriten entfernen"
                                    x-on:click.prevent="if(confirm('Wirklich aus Favoriten entfernen?')) { removeFromFavorites(recipe.id || recipe.id_external || recipe.id_recipe) }"
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

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
            <template x-for="recipe in availableRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden relative">
                        <!-- Image -->
                        <div class="aspect-w-16 aspect-h-9">
                            <template x-if="recipe.media && recipe.media.preview && recipe.media.preview.length > 0">
                                <img :src="recipe.media.preview[0]" alt="Rezept Bild" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="recipe.images && recipe.images.length > 0">
                                <img :src="recipe.images[0]" alt="Rezept Bild" class="w-full h-48 object-cover object-center">
                            </template>
                            <template x-if="(!recipe.media || !recipe.media.preview || recipe.media.preview.length === 0) && (!recipe.images || recipe.images.length === 0)">
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
                                    x-on:click.prevent="$dispatch('openRecipeModal', [recipe.id_recipe || recipe.id_external || recipe.id])"
                                />

                                <!-- Add to Book -->
                                <x-filament::icon-button
                                    icon="heroicon-o-plus"
                                    color="success"
                                    tooltip="Zum Buch hinzufügen"
                                    x-on:click.prevent="addToBook(recipe.id || recipe.id_external || recipe.id_recipe)"
                                />

                                <!-- Add to Favorites -->
                                <x-filament::icon-button
                                    icon="heroicon-o-heart"
                                    color="gray"
                                    tooltip="Zu Favoriten hinzufügen"
                                    x-on:click.prevent="addToFavorites(recipe.id || recipe.id_external || recipe.id_recipe)"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>
</div>
