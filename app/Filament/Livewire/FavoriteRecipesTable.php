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
        $collection = collect();
        if ($recipes instanceof \Illuminate\Support\Collection) {
            $arr = $recipes->all();
            $ids_recipe = array_filter(array_map(function($r) { return is_array($r) ? ($r['id_recipe'] ?? null) : null; }, $arr));
            $ids_external = array_filter(array_map(function($r) { return is_array($r) ? ($r['id_external'] ?? null) : null; }, $arr));
            if (count($ids_recipe) > 0 || count($ids_external) > 0) {
                $query = Recipe::query();
                if (count($ids_recipe) > 0) $query->orWhereIn('id_recipe', $ids_recipe);
                if (count($ids_external) > 0) $query->orWhereIn('id_external', $ids_external);
                $collection = $query->get();
            } else {
                $collection = collect(array_filter($arr, function($r) { return $r instanceof Recipe; }));
            }
        } elseif (is_array($recipes)) {
            $ids_recipe = array_filter(array_map(function($r) { return is_array($r) ? ($r['id_recipe'] ?? null) : null; }, $recipes));
            $ids_external = array_filter(array_map(function($r) { return is_array($r) ? ($r['id_external'] ?? null) : null; }, $recipes));
            if (count($ids_recipe) > 0 || count($ids_external) > 0) {
                $query = Recipe::query();
                if (count($ids_recipe) > 0) $query->orWhereIn('id_recipe', $ids_recipe);
                if (count($ids_external) > 0) $query->orWhereIn('id_external', $ids_external);
                $collection = $query->get();
            } else {
                $collection = collect(array_filter($recipes, function($r) { return $r instanceof Recipe; }));
            }
        } else {
            $collection = collect();
        }
        // Final guard: only allow Recipe objects
        return $collection->filter(function($r) { return $r instanceof Recipe; })->values();
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
        if ($this->recipes instanceof \Illuminate\Support\Collection) {
            $this->recipes = $this->recipes->filter(function($r) { return is_object($r); })->values();
        }
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
        $this->refreshRecipes();
    }

    public function addToBook($id)
    {
        $book = Book::find($this->bookId);
        if (!$book || !$book->patient) return;
        // Accept either internal or external id
        $recipe = \App\Models\Recipe::where('id_recipe', $id)->orWhere('id_external', $id)->first();
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
        $recipesCollection = $this->ensureRecipeCollection($this->recipes)->filter(function($r) { return $r instanceof Recipe; });
        $filtered = $recipesCollection->filter(function($r) use ($recipe) {
            return $r->id_recipe != $recipe->id_recipe && $r->id_external != $recipe->id_external;
        });
        $this->recipes = $filtered->values();
        $this->refreshRecipes();
    }

    public function addFavoriteRecipe($externalId)
    {
        $book = Book::find($this->bookId);
        if (!$book || !$book->patient) return;
        $patient = $this->getBookPatient();
        $settings = $patient->settings ?? [];
        $favorites = $settings['favorites'] ?? [];
        $bookRecipeIds = $book->recipes()->pluck('id_recipe')->toArray();
        $recipe = \App\Models\Recipe::where('id_external', $externalId)->orWhere('id_recipe', $externalId)->first();
        if (!$recipe) return;
        if (in_array($recipe->id_recipe, $bookRecipeIds)) return; // Do not add to UI if in book
        // Only allow objects in $this->recipes
        if (!($this->recipes instanceof \Illuminate\Support\Collection)) {
            $this->recipes = $this->ensureRecipeCollection($this->recipes);
        }
        $this->recipes = $this->recipes->filter(function($r) { return $r instanceof Recipe; })->values();
        $alreadyPresent = $this->recipes->contains(function ($r) use ($recipe) {
            return $r instanceof Recipe && $r->id_recipe == $recipe->id_recipe;
        });
        if ($alreadyPresent) return;
        if ($recipe instanceof Recipe) {
            $this->recipes = $this->recipes->prepend($recipe);
        }
        $this->recipes = $this->ensureRecipeCollection($this->recipes);
        // Always refresh to remove from UI if now in book
        $this->refreshRecipes();
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
