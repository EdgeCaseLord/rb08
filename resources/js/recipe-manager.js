export default (initialData) => ({
    bookRecipes: initialData.bookRecipes || [],
    favoriteRecipes: initialData.favoriteRecipes || [],
    availableRecipes: initialData.availableRecipes || [],
    bookRecipeCounts: initialData.bookRecipeCounts || {starter: 0, main_course: 0, dessert: 0},
    recipeLimits: initialData.recipeLimits || {starter: 5, main_course: 5, dessert: 5},
    bookId: initialData.bookId,

    // Find and remove recipe from array
    findAndRemove(array, id) {
        const index = array.findIndex(r =>
            (r.id || r.id_external || r.id_recipe) == id
        );
        if (index === -1) return null;
        return array.splice(index, 1)[0];
    },

    // Get recipe course
    getRecipeCourse(recipe) {
        const categories = recipe.category || [];
        // Simplified course detection - adjust based on your logic
        if (categories.includes('Vorspeise')) return 'starter';
        if (categories.includes('Dessert')) return 'dessert';
        return 'main_course';
    },

    // Add recipe to book
    addToBook(recipeId) {
        const recipe = this.findAndRemove(this.availableRecipes, recipeId);
        if (!recipe) return;

        const course = this.getRecipeCourse(recipe);

        // Check limits (instant)
        if (this.bookRecipeCounts[course] >= this.recipeLimits[course]) {
            alert(`Limit erreicht für ${course}! Max: ${this.recipeLimits[course]}`);
            // Put recipe back
            this.availableRecipes.unshift(recipe);
            return;
        }

        // Update UI immediately
        this.bookRecipes.push(recipe);
        this.bookRecipeCounts[course]++;

        // Persist to server (non-blocking)
        this.persistOperation('add_to_book', recipeId);
    },

    // Remove recipe from book
    removeFromBook(recipeId) {
        const recipe = this.findAndRemove(this.bookRecipes, recipeId);
        if (!recipe) return;

        const course = this.getRecipeCourse(recipe);
        this.bookRecipeCounts[course]--;

        // Move to appropriate list
        if (recipe.is_favorite) {
            this.favoriteRecipes.push(recipe);
        } else {
            this.availableRecipes.unshift(recipe);
        }

        this.persistOperation('remove_from_book', recipeId);
    },

    // Add to favorites
    addToFavorites(recipeId) {
        const recipe = this.findAndRemove(this.availableRecipes, recipeId);
        if (!recipe) return;

        recipe.is_favorite = true;
        this.favoriteRecipes.push(recipe);

        this.persistOperation('add_to_favorites', recipeId);
    },

    // Remove from favorites
    removeFromFavorites(recipeId) {
        const recipe = this.findAndRemove(this.favoriteRecipes, recipeId);
        if (!recipe) return;

        recipe.is_favorite = false;
        this.availableRecipes.unshift(recipe);

        this.persistOperation('remove_from_favorites', recipeId);
    },

    // Persist operation to server
    persistOperation(operation, recipeId) {
        fetch(`/api/books/${this.bookId}/recipe-operations`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ operation, recipeId })
        }).catch(err => console.error('Failed to persist:', err));
    }
});
