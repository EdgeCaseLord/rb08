<div x-data="recipeManager({
    bookRecipes: @json($bookRecipes),
    favoriteRecipes: @json($favoriteRecipes),
    availableRecipes: @json($availableRecipes),
    bookId: @json($bookId)
})">
    @props([
        'bookRecipes' => [],
        'favoriteRecipes' => [],
        'availableRecipes' => [],
        'showActions' => false,
        'bookId' => null,
        'isBookRecipes' => false,
        'context' => 'book',
        'hasMore' => true,
        'loading' => false,
    ])

    @php
        use App\Filament\Livewire\AvailableRecipesTable;
    @endphp

    <!-- Book Recipes Section -->
    <template x-if="bookRecipes.length">
        <div>
            <div class="mb-2 text-gray-700 font-semibold">Rezepte im Buch</div>
            <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
                <template x-for="recipe in bookRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                    <div class="mb-4 break-inside-avoid">
                        <x-filament.recipe-resource.recipe-card
                            :recipe="recipe"
                            :context="'book'"
                            :bookId="bookId"
                            :isBookRecipes="true"
                            :showActions="true"
                            @move="moveRecipe($event.detail)"
                        />
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Favorite Recipes Section -->
    <template x-if="favoriteRecipes.length">
        <div>
            <div class="mb-2 text-gray-700 font-semibold">Favoriten</div>
            <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
                <template x-for="recipe in favoriteRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                    <div class="mb-4 break-inside-avoid">
                        <x-filament.recipe-resource.recipe-card
                            :recipe="recipe"
                            :context="'favorites'"
                            :bookId="bookId"
                            :isBookRecipes="false"
                            :showActions="true"
                            @move="moveRecipe($event.detail)"
                        />
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Available Recipes Section -->
    <template x-if="availableRecipes.length">
        <div>
            <div class="mb-2 text-gray-700 font-semibold">Verfügbare Rezepte</div>
            <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
                <template x-for="recipe in availableRecipes" :key="recipe.id || recipe.id_external || recipe.id_recipe">
                    <div class="mb-4 break-inside-avoid">
                        <x-filament.recipe-resource.recipe-card
                            :recipe="recipe"
                            :context="'available'"
                            :bookId="bookId"
                            :isBookRecipes="false"
                            :showActions="showActions"
                            @move="moveRecipe($event.detail)"
                        />
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- Loading, Load More, No More -->
    @if($loading)
        <div class="flex justify-center py-4">
            <svg class="animate-spin h-6 w-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>
    @endif
    @if($hasMore && !$loading)
        <div class="flex justify-center py-4">
            <button wire:click="loadMore" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Mehr laden</button>
        </div>
    @endif
    @if(!$hasMore && !$loading)
        <div class="text-center text-gray-400 py-2">Keine weiteren Rezepte.</div>
    @endif
</div>

<script>
function recipeManager(initial) {
    return {
        bookRecipes: initial.bookRecipes,
        favoriteRecipes: initial.favoriteRecipes,
        availableRecipes: initial.availableRecipes,
        bookId: initial.bookId,
        moveRecipe({recipe, from, to}) {
            // Remove from source
            this[from] = this[from].filter(r => (r.id || r.id_external || r.id_recipe) !== (recipe.id || recipe.id_external || recipe.id_recipe));
            // Add to target
            this[to].push(recipe);
            // Persist to server
            fetch(`/api/recipes/move`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({recipe_id: recipe.id || recipe.id_external || recipe.id_recipe, from, to, book_id: this.bookId})
            }).then(r => {
                if (!r.ok) throw new Error('Server error');
            }).catch(() => {
                // Revert UI
                this[to] = this[to].filter(r => (r.id || r.id_external || r.id_recipe) !== (recipe.id || recipe.id_external || recipe.id_recipe));
                this[from].push(recipe);
                alert('Fehler beim Verschieben!');
            });
        }
    }
}
</script>
