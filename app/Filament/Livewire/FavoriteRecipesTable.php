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
        $book = Book::find($this->bookId);
        if (!$book || !$book->patient) { $this->recipes = collect(); return; }
        $patient = $this->getBookPatient();
        $settings = $patient->settings ?? [];
        $favorites = $settings['favorites'] ?? [];
        $bookRecipeIds = $book->recipes()->pluck('id_recipe')->toArray();
        $recipes = Recipe::whereIn('id_external', $favorites)
            ->whereNotIn('id_recipe', $bookRecipeIds)
            ->get();
        $this->recipes = $this->ensureRecipeCollection($recipes);
    }

    private function normalizeRecipes()
    {
        // No-op: $this->recipes is always a collection of objects now
    }

    public function removeFromFavorites($id)
    {
        $user = $this->getBookPatient();
        if (!$user) return;
        $user->removeFromFavorites($id);
        $this->dispatch('recipeRemovedFromFavorites', $id);
        // Remove from favorites UI immediately
        $this->recipes = $this->ensureRecipeCollection($this->recipes);
        $this->recipes = $this->recipes->reject(function($r) use ($id) {
            return $r->id_recipe == $id || $r->id_external == $id;
        })->values();
        // Do not call refreshRecipes here
    }

    public function addToBook($id)
    {
        $book = Book::find($this->bookId);
        if (!$book || !$book->patient) return;
        // Accept either internal or external id
        $recipe = Recipe::where('id_recipe', $id)->orWhere('id_external', $id)->first();
        if (!$recipe) return;
        try {
            $book->addRecipe($recipe->id_recipe);
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Nicht hinzugefügt')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return;
        }
        $this->dispatch('recipeAddedToBook', $recipe->id_external ?? $recipe->id_recipe);
        // Remove from favorites UI immediately
        $this->recipes = $this->ensureRecipeCollection($this->recipes);
        $this->recipes = $this->recipes->reject(function($r) use ($recipe) {
            return $r->id_recipe == $recipe->id_recipe || $r->id_external == $recipe->id_external;
        })->values();
        // Do not call refreshRecipes here
    }

    public function addFavoriteRecipe($externalId)
    {
        $book = Book::find($this->bookId);
        if (!$book || !$book->patient) return;
        $patient = $this->getBookPatient();
        $settings = $patient->settings ?? [];
        $favorites = $settings['favorites'] ?? [];
        $bookRecipeIds = $book->recipes()->pluck('id_recipe')->toArray();
        $recipe = Recipe::where('id_external', $externalId)->orWhere('id_recipe', $externalId)->first();
        if (!$recipe) return;
        if (in_array($recipe->id_recipe, $bookRecipeIds)) return; // Do not add to UI if in book
        $this->recipes = $this->ensureRecipeCollection($this->recipes);
        // Always prepend, even if already present, to ensure UI update
        $this->recipes = $this->recipes->reject(function ($r) use ($recipe) {
            return $r->id_recipe == $recipe->id_recipe;
        });
        $this->recipes = $this->recipes->prepend($recipe);
        // No need to call ensureRecipeCollection again, as all are Recipe objects
        // Do not call refreshRecipes here, to avoid UI flicker and ensure instant update
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
