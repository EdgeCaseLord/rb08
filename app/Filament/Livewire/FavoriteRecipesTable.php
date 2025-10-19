<?php

namespace App\Filament\Livewire;

use Livewire\Component;
use App\Models\Book;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;

class FavoriteRecipesTable extends Component
{
    public $bookId;
    public $recipes = [];
    public $showActions = true;

    protected $listeners = [
        'recipeRemovedFromBook' => 'refreshRecipes',
        'recipeAddedToFavorites' => 'addFavoriteRecipe',
        'recipeRemovedFromFavorites' => 'refreshRecipes',
    ];

    public function mount($bookId)
    {
        $this->bookId = $bookId;
        $this->refreshRecipes();
        $this->recipes = $this->ensureRecipeCollection($this->recipes);
    }

    protected function getBookPatient()
    {
        $book = Book::find($this->bookId);
        if (!$book || !$book->patient) return null;
        $user = $book->patient;
        if (!is_object($user)) {
            $user = \App\Models\User::find($book->patient_id);
            if (!is_object($user)) return null;
        }
        return $user;
    }

    private function ensureRecipeCollection($recipes)
    {
        if ($recipes instanceof \Illuminate\Support\Collection) {
            return $recipes->filter(function($r) { return $r instanceof Recipe; })->values();
        } elseif (is_array($recipes)) {
            return collect($recipes)->filter(function($r) { return $r instanceof Recipe; })->values();
        } else {
            return collect();
        }
    }

    public function refreshRecipes()
    {
        // Skip database queries for immediate UI response
        // The UI will be updated by the background job
        $this->recipes = collect();
    }

    private function normalizeRecipes()
    {
        // No-op: $this->recipes is always a collection of objects now
    }

    public function removeFromFavorites($id)
    {
        // Immediate UI update - remove from favorites
        $this->recipes = collect($this->recipes)->reject(function($r) use ($id) {
            return $r->id_recipe == $id || $r->id_external == $id;
        })->values();

        // Show immediate feedback FIRST
        \Filament\Notifications\Notification::make()
            ->title(__('Aus Favoriten entfernt'))
            ->body('Das Rezept wird im Hintergrund verarbeitet.')
            ->success()
            ->send();

        // Dispatch background job for database operations (user ID will be resolved in job)
        \App\Jobs\ProcessRecipeOperation::dispatch('remove_from_favorites', $id, $this->bookId);

        // Dispatch UI event
        $this->dispatch('recipeRemovedFromFavorites', $id);
    }

    public function addToBook($id)
    {
        // Get recipe data BEFORE removing from array (no DB query)
        $recipe = collect($this->recipes)->first(function($r) use ($id) {
            return $r->id_recipe == $id || $r->id_external == $id;
        });
        if (!$recipe) return;

        // Immediate UI update - remove from favorites
        $this->recipes = $this->ensureRecipeCollection($this->recipes)
            ->reject(function($r) use ($recipe) {
                return $r->id_recipe == $recipe->id_recipe || $r->id_external == $recipe->id_external;
            })->values();

        // Show immediate feedback FIRST
        \Filament\Notifications\Notification::make()
            ->title(__('Rezept hinzugefügt'))
            ->body('Das Rezept wird im Hintergrund verarbeitet.')
            ->success()
            ->send();

        // Dispatch background job for heavy operations
        \App\Jobs\ProcessRecipeOperation::dispatch('add_to_book', $recipe->id_external ?? $recipe->id_recipe, $this->bookId);

        // Dispatch UI event
        $this->dispatch('recipeAddedToBook', $recipe->id_external ?? $recipe->id_recipe);
    }

    public function addFavoriteRecipe($externalId)
    {
        // Create minimal recipe entry without database queries
        $this->recipes = collect($this->recipes)
            ->reject(function ($r) use ($externalId) {
                return ($r->id_external ?? null) == $externalId || ($r->id_recipe ?? null) == $externalId;
            })->prepend((object)[
                'id_external' => $externalId,
                'id_recipe' => $externalId,
                'title' => 'Recipe ' . $externalId,
                'category' => [],
                'diets' => [],
                'allergens' => []
            ])->values();
    }

    public function render()
    {
        // Always pass a collection of objects to the view
        $recipes = $this->ensureRecipeCollection($this->recipes);
        return view('livewire.favorite-recipes-table', [
            'recipes' => $recipes,
            'showActions' => $this->showActions,
            'bookId' => $this->bookId,
        ]);
    }
}
