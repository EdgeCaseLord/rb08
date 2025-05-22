<?php

namespace App\Filament\Livewire;

use Livewire\Component;
use App\Services\CookButlerService;
use App\Models\Book;
use App\Models\Recipe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RecipesTable extends Component
{
    protected $cookButlerService;
    public $bookRecipes = [];
    public $favoriteRecipes = [];
    public $availableRecipes = [];
    public $showActions = false;
    public $bookId = null;
    public $isBookRecipes = false;
    public $context = 'book';
    public $page = 1;
    public $perPage = 10;
    public $loading = false;
    public $hasMore = true;
    public $totalLoaded = 0;
    public $maxRecipes = 100;

    protected function ensureCookButlerService()
    {
        if (!$this->cookButlerService) {
            $this->cookButlerService = app(CookButlerService::class);
        }
    }

    public function mount($showActions = false, $bookId = null, $isBookRecipes = false, $context = 'book', $availableRecipes = [])
    {
        $this->ensureCookButlerService();
        Log::info('RecipesTable mount called', [
            'showActions' => $showActions,
            'bookId' => $bookId,
            'isBookRecipes' => $isBookRecipes,
            'context' => $context,
            'availableRecipes' => $availableRecipes,
        ]);
        $this->showActions = $showActions;
        $this->bookId = $bookId;
        $this->isBookRecipes = $isBookRecipes;
        $this->context = $context;
        $this->page = 1;
        $this->hasMore = true;
        $this->loading = false;
        $this->bookRecipes = collect();
        $this->favoriteRecipes = collect();
        $this->availableRecipes = [];
        $this->totalLoaded = 0;

        $user = Auth::user();
        if ($this->bookId) {
            $book = Book::find($this->bookId);
            if ($book) {
                $this->bookRecipes = $book->recipes()->get()->map(function($r) {
                    return \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($r);
                });
                if ($book->patient) {
                    $favIds = $book->patient->settings['favorites'] ?? [];
                    $this->favoriteRecipes = Recipe::whereIn('id_recipe', $favIds)->get()->map(function($r) {
                        return \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($r);
                    });
                }
            }
        } else if ($user) {
            $favIds = $user->settings['favorites'] ?? [];
            $this->favoriteRecipes = Recipe::whereIn('id_recipe', $favIds)->get()->map(function($r) {
                return \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($r);
            });
        }
        if (!$this->isBookRecipes) {
            $this->loadMore();
        }
    }

    public function loadMore()
    {
        $this->ensureCookButlerService();
        Log::info('RecipesTable loadMore called', [
            'bookId' => $this->bookId,
            'isBookRecipes' => $this->isBookRecipes,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'context' => $this->context,
        ]);
        if ($this->loading || !$this->hasMore) return;
        $this->loading = true;
        $user = null;
        if ($this->bookId) {
            $book = Book::find($this->bookId);
            if ($book && $book->patient) {
                $user = $book->patient;
            }
        }
        if (!$user) {
            $user = Auth::user();
        }
        if (!$user) {
            $this->loading = false;
            Log::warning('RecipesTable loadMore: No user found');
            return;
        }
        $offset = ($this->page - 1) * $this->perPage;
        $filters = [];
        $result = $this->cookButlerService->fetchAvailableRecipesForPatient($user, $filters, $this->perPage, $offset);
        Log::info('RecipesTable loadMore: API result', [
            'result' => $result,
        ]);
        $recipeIds = $result['recipe_ids'] ?? [];
        $total = $result['total']['value'] ?? 0;
        if (empty($recipeIds)) {
            $this->hasMore = false;
            $this->loading = false;
            Log::info('RecipesTable loadMore: No recipe IDs returned');
            return;
        }
        $details = $this->cookButlerService->fetchRecipeDetailsBatch($recipeIds);
        // Filter out recipes already in the book
        $bookRecipeIds = collect($this->bookRecipes)->pluck('id_external')->all();
        $filtered = array_filter($details, function ($recipe) use ($bookRecipeIds) {
            $id = $recipe['id_external'] ?? $recipe['id'] ?? $recipe['id_recipe'] ?? null;
            return $id && !in_array($id, $bookRecipeIds);
        });
        $this->availableRecipes = array_merge($this->availableRecipes, $filtered);
        $this->totalLoaded = count($this->availableRecipes);
        $this->page++;
        if ($this->totalLoaded >= $this->maxRecipes || $this->totalLoaded >= $total) {
            $this->hasMore = false;
        }
        $this->loading = false;
        Log::info('RecipesTable loadMore: Recipes loaded', [
            'totalLoaded' => $this->totalLoaded,
            'hasMore' => $this->hasMore,
        ]);
    }

    protected function getRecipeId($recipe)
    {
        return $recipe['id_external'] ?? $recipe['id'] ?? $recipe['id_recipe'] ?? null;
    }

    protected function syncAvailableRecipesWithBook()
    {
        $bookRecipeIds = collect($this->bookRecipes)->map(function ($r) {
            return $this->getRecipeId(is_array($r) ? $r : $r->toArray());
        })->filter()->all();
        $this->availableRecipes = array_values(array_filter($this->availableRecipes, function ($recipe) use ($bookRecipeIds) {
            $id = $this->getRecipeId($recipe);
            return $id && !in_array($id, $bookRecipeIds);
        }));
    }

    public function removeRecipe($id)
    {
        $user = Auth::user();
        if (!$user || !$this->bookId) return;
        $book = Book::find($this->bookId);
        if (!$book) return;
        $recipe = Recipe::find($id);
        if (!$recipe) return;
        $book->recipes()->detach($recipe->id_recipe);
        $this->bookRecipes = $book->recipes()->get()->map(function($r) {
            return \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($r);
        });
        // Add back to availableRecipes if not present and not in the book
        $recipeId = $recipe->id_external;
        $stillInBook = collect($this->bookRecipes)->contains(function ($r) use ($recipeId) {
            return $this->getRecipeId($r) == $recipeId;
        });
        $alreadyPresent = collect($this->availableRecipes)->contains(function ($r) use ($recipeId) {
            return $this->getRecipeId($r) == $recipeId;
        });
        if (!$alreadyPresent && !$stillInBook) {
            $this->availableRecipes[] = \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($recipe);
        }
        $this->syncAvailableRecipesWithBook();
    }

    public function addToBook($externalId)
    {
        $user = Auth::user();
        if (!$user || !$this->bookId) return;
        $book = Book::find($this->bookId);
        if (!$book) return;
        $recipe = Recipe::where('id_external', $externalId)->first();
        if (!$recipe) {
            // Try to find the recipe in availableRecipes
            $apiRecipe = collect($this->availableRecipes)->first(function ($r) use ($externalId) {
                return $this->getRecipeId($r) == $externalId;
            });
            if ($apiRecipe) {
                $recipe = \App\Filament\Livewire\AvailableRecipesTable::arrayToRecipeModel($apiRecipe);
            } else {
                return;
            }
        }
        $book->addRecipe($recipe->id_recipe);
        $this->bookRecipes = $book->recipes()->get()->map(function($r) {
            return \App\Filament\Livewire\AvailableRecipesTable::recipeModelToArray($r);
        });
        $this->syncAvailableRecipesWithBook();
    }

    public function render()
    {
        return view('livewire.recipes-table');
    }
}
