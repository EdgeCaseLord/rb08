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
    public $currentCourse = 'starter';
    public $courseOrder = ['starter', 'main_course', 'dessert'];

    protected $listeners = [
        'recipeAddedToBook' => 'refreshRecipes',
        // Do not reload all available recipes on removal
        // 'recipeRemovedFromBook' => 'refreshRecipes',
        // 'recipeAddedToFavorites' => 'refreshRecipes',
        // 'recipeRemovedFromFavorites' => 'refreshRecipes',
        'recipeRemovedFromFavorites' => 'updateFavoriteStatus',
        'bookUpdated' => 'refreshRecipes',
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
        // Immediate UI update - remove from local array
        $this->recipes = array_values(array_filter($this->recipes, function ($r) use ($id) {
            return ($r['id_recipe'] ?? null) != $id && ($r['id_external'] ?? null) != $id;
        }));

        // Show immediate feedback FIRST
        \Filament\Notifications\Notification::make()
            ->title(__('Rezept entfernt'))
            ->body('Das Rezept wird im Hintergrund verarbeitet.')
            ->success()
            ->send();

        // Dispatch background job for heavy operations (truly async)
        \App\Jobs\ProcessRecipeOperation::dispatch('remove_from_book', $id, $this->bookId);

        // Check if recipe is a favorite and dispatch accordingly
        $recipe = Recipe::where('id_recipe', $id)->first();
        if (!$recipe) {
            $recipe = Recipe::where('id_external', $id)->first();
        }

        if ($recipe) {
            $user = $this->getBookPatient();
            $settings = $user ? ($user->settings ?? []) : [];
            $favorites = $settings['favorites'] ?? [];

            if (in_array($recipe->id_external, $favorites) || in_array($recipe->id_recipe, $favorites)) {
                // Recipe is a favorite - add to favorites
                $this->dispatch('recipeAddedToFavorites', $recipe->id_external ?? $recipe->id_recipe);
            } else {
                // Recipe is not a favorite - add to available recipes
                $this->dispatch('recipeRemovedFromBook', $id);
            }
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

        // Dispatch background job for database operations
        \App\Jobs\ProcessRecipeOperation::dispatch('add_to_favorites', (string)$dbRecipe->id_external, null, $user->id);

        // Dispatch UI event
        $this->dispatch('recipeAddedToFavorites', (string)$dbRecipe->id_external);

        // Show immediate feedback
        \Filament\Notifications\Notification::make()
            ->title(__('Zu Favoriten hinzugefügt'))
            ->body('Das Rezept wird im Hintergrund verarbeitet.')
            ->success()
            ->send();
    }

    public function removeFromFavorites($id)
    {
        $user = $this->getBookPatient();
        if (!$user) return;
        $id = (string) $id;
        $recipe = \App\Models\Recipe::where('id_recipe', $id)->orWhere('id_external', $id)->first();
        if (!$recipe) return;

        // Dispatch background job for database operations
        \App\Jobs\ProcessRecipeOperation::dispatch('remove_from_favorites', (string)$recipe->id_external, null, $user->id);

        // Dispatch UI event
        $this->dispatch('recipeRemovedFromFavorites', (string)$recipe->id_external);

        // Show immediate feedback
        \Filament\Notifications\Notification::make()
            ->title(__('Aus Favoriten entfernt'))
            ->body('Das Rezept wird im Hintergrund verarbeitet.')
            ->success()
            ->send();
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

    public function switchCourse($course)
    {
        if (in_array($course, $this->courseOrder)) {
            $this->currentCourse = $course;
        }
    }

    public function nextCourse()
    {
        $idx = array_search($this->currentCourse, $this->courseOrder);
        if ($idx !== false && $idx < count($this->courseOrder) - 1) {
            $this->currentCourse = $this->courseOrder[$idx + 1];
        }
    }

    public function prevCourse()
    {
        $idx = array_search($this->currentCourse, $this->courseOrder);
        if ($idx !== false && $idx > 0) {
            $this->currentCourse = $this->courseOrder[$idx - 1];
        }
    }

    public function render()
    {
        return view('livewire.book-recipes-table', [
            'recipes' => $this->recipes,
            'showActions' => $this->showActions,
            'bookId' => $this->bookId,
            'currentCourse' => $this->currentCourse,
            'courseOrder' => $this->courseOrder,
        ]);
    }
}
