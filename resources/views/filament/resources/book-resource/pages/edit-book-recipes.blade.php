<div x-data="recipeManager(@js([
    'bookRecipes' => $bookRecipes,
    'favoriteRecipes' => $favoriteRecipes,
    'availableRecipes' => $availableRecipes,
    'bookRecipeCounts' => $bookRecipeCounts,
    'recipeLimits' => $recipeLimits,
    'bookId' => $bookId
]))">

    <!-- Book Recipes Section -->
    <x-filament::section>
        <x-slot name="heading">
            Rezepte im Buch (<span x-text="bookRecipes.length"></span>)
        </x-slot>

        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
            <template x-for="recipe in bookRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                <div class="mb-4 break-inside-avoid">
                    <!-- Recipe card component -->
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 x-text="recipe.title"></h3>
                        <button @click="removeFromBook(recipe.id || recipe.id_external || recipe.id_recipe)"
                                class="mt-2 px-3 py-1 bg-red-500 text-white rounded">
                            Entfernen
                        </button>
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
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 x-text="recipe.title"></h3>
                        <button @click="addToBook(recipe.id || recipe.id_external || recipe.id_recipe)"
                                class="mt-2 px-3 py-1 bg-blue-500 text-white rounded">
                            Zum Buch
                        </button>
                        <button @click="removeFromFavorites(recipe.id || recipe.id_external || recipe.id_recipe)"
                                class="mt-2 px-3 py-1 bg-gray-500 text-white rounded">
                            Aus Favoriten
                        </button>
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
                    <div class="bg-white rounded-lg shadow p-4">
                        <h3 x-text="recipe.title"></h3>
                        <button @click="addToBook(recipe.id || recipe.id_external || recipe.id_recipe)"
                                class="mt-2 px-3 py-1 bg-blue-500 text-white rounded">
                            Zum Buch
                        </button>
                        <button @click="addToFavorites(recipe.id || recipe.id_external || recipe.id_recipe)"
                                class="mt-2 px-3 py-1 bg-yellow-500 text-white rounded">
                            Favorit
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::section>
</div>
