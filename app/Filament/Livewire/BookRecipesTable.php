<?php

namespace App\Filament\Livewire;

use Livewire\Component;
use App\Models\Book;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BookRecipesTable extends Component
{
    public $bookId;
    public $recipes = [];
    public $showActions = true;

    protected $listeners = [
        'recipeAddedToBook' => 'refreshRecipes',
        // Do not reload all available recipes on removal
        // 'recipeRemovedFromBook' => 'refreshRecipes',
        // 'recipeAddedToFavorites' => 'refreshRecipes',
        // 'recipeRemovedFromFavorites' => 'refreshRecipes',
        'recipeRemovedFromFavorites' => 'updateFavoriteStatus',
    ];

    public function mount($bookId)
    {
        $this->bookId = $bookId;
        $this->refreshRecipes();
    }

    public function refreshRecipes()
    {
        $book = Book::find($this->bookId);
        Log::info('BookRecipesTable: refreshRecipes', ['bookId' => $this->bookId, 'book' => $book]);
        if (!$book) { $this->recipes = []; $this->dispatch('bookRecipesChanged'); return; }
        $recipes = $book->recipes()->get();
        $this->recipes = array_map(function($r) {
            return \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($r);
        }, $recipes->all());
        $idRecipes = array_map(function($r) { return $r['id_recipe'] ?? null; }, $this->recipes);
        Log::info('BookRecipesTable: recipes: ' . implode(',', $idRecipes));
        $this->dispatch('bookRecipesChanged');
    }

    public function removeRecipe($id)
    {
        $book = Book::find($this->bookId);
        if ($book) {
            // Always fetch as model
            $recipe = Recipe::where('id_recipe', $id)->first();
            if (!$recipe) {
                $recipe = Recipe::where('id_external', $id)->first();
            }
            if (!$recipe) return;
            $internalId = $recipe->id_recipe;
            // Remove from book
            $book->removeRecipe($internalId);
            // Update status if not 'Warten auf Versand'
            if ($book->status !== 'Warten auf Versand') {
                $book->status = 'Geändert nach Versand';
                $book->save();
                $this->dispatch('bookStatusUpdated', id: $book->id, status: $book->status);
            }
            // Remove from local array (array of arrays)
            $this->recipes = array_values(array_filter($this->recipes, function ($r) use ($internalId) {
                return ($r['id_recipe'] ?? null) != $internalId;
            }));
            // Dispatch to available/favs as needed
            $user = $this->getBookPatient();
            $settings = $user ? ($user->settings ?? []) : [];
            $favorites = $settings['favorites'] ?? [];
            if (in_array($recipe->id_external, $favorites) || in_array($recipe->id_recipe, $favorites)) {
                $this->dispatch('recipeAddedToFavorites', $recipe->id_external ?? $recipe->id_recipe);
            } else {
                // Convert model to array for available pane
                $arr = \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($recipe);
                // Only prepend to available recipes UI, do not reload all
                $this->dispatch('prependAvailableRecipe', $arr['id'] ?? $arr['id_external'] ?? $arr['id_recipe']);
            }
            $this->dispatch('bookRecipesChanged');
        }
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

    public function addToFavorites($id)
    {
        $user = $this->getBookPatient();
        if (!$user) return;
        $id = (string) $id;
        $recipe = null;
        foreach ($this->recipes as $r) {
            if ((string)($r['id_recipe'] ?? null) === $id || (string)($r['id_external'] ?? null) === $id) {
                $recipe = $r;
                break;
            }
        }
        if (!$recipe) return;
        $dbRecipe = \App\Filament\Livewire\AvailableRecipesTable::arrayToRecipeModel($recipe);
        if (!$dbRecipe) return;
        $user->addToFavorites((string)$dbRecipe->id_external);
        $this->dispatch('recipeAddedToFavorites', (string)$dbRecipe->id_external);
    }

    public function removeFromFavorites($id)
    {
        $user = $this->getBookPatient();
        if (!$user) return;
        $id = (string) $id;
        $recipe = \App\Models\Recipe::where('id_recipe', $id)->orWhere('id_external', $id)->first();
        if (!$recipe) return;
        $user->removeFromFavorites((string)$recipe->id_external);
        $this->dispatch('recipeRemovedFromFavorites', (string)$recipe->id_external);
    }

    public function confirmRemoveFromFavorites($id)
    {
        $this->removeFromFavorites($id);
    }

    public function updateFavoriteStatus($externalId)
    {
        // No replacement needed, as all recipes are arrays now
    }

    // Utility function for hardening json_decode for all recipe fields
    private static function normalizeField($value, $asObject = false) {
        $result = is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
        return $asObject ? (object)$result : $result;
    }

    public function render()
    {
        return view('livewire.book-recipes-table', [
            'recipes' => $this->recipes,
            'showActions' => $this->showActions,
            'bookId' => $this->bookId,
        ]);
    }
}
