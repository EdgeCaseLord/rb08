<?php

namespace App\Filament\Livewire;

use Livewire\Component;
use App\Models\Book;
use App\Models\Recipe;
use App\Services\CookButlerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AvailableRecipesTable extends Component
{
    public $bookId;
    public $recipes = [];
    public $showActions = true;
    public $page = 1;
    public $perPage = 10;
    public $hasMore = true;
    public $loading = false;
    public $filterTitle = '';
    public $filterDifficulty = [];
    public $filterCourse = [];
    public $filterIngredients = '';
    public $filterDiets = [];
    public $modalRecipeId = null;
    public $showRecipeModal = false;
    public $modalRecipe = null;
    public $hasFormsModalRendered = false;
    public $defaultAction = null;
    public $filterAllergen = [];
    public $filterCategory = [];
    public $filterCountry = [];
    public $filterMaxTime = [];
    public $filterOffset = 0;
    public $filterRandomizeOffset = false;
    public $refreshKey = 0;
    protected $cookButlerService;

    protected $listeners = [
        'recipeRemovedFromBook' => 'addToAvailableRecipes',
        'addToBook',
        'openRecipeModal' => 'openRecipeModal',
        'recipeRemovedFromFavorites' => 'prependAvailableRecipe',
        'prependAvailableRecipe' => 'prependAvailableRecipe',
    ];

    public function mount($bookId, CookButlerService $cookButlerService)
    {
        Log::debug('AvailableRecipesTable mount', ['bookId' => $bookId]);
        $this->bookId = $bookId;
        $this->cookButlerService = $cookButlerService;
        $this->recipes = [];
        $this->page = 1;
        $this->hasMore = true;
        $this->filterOffset = 0;
        // Load saved filter preferences if present
        $patient = $this->getBookPatient();
        Log::debug('AvailableRecipesTable mount patient', ['patient' => $patient ? $patient->id : null]);
        if ($patient && !empty($patient['settings']['recipe_filter_set'])) {
            $prefs = $patient['settings']['recipe_filter_set'];
            foreach ([
                'filterTitle', 'filterIngredients', 'filterAllergen', 'filterCategory', 'filterCountry', 'filterCourse', 'filterDiets', 'filterDifficulty', 'filterMaxTime'
            ] as $key) {
                if (array_key_exists($key, $prefs)) {
                    $this->$key = $prefs[$key];
                }
            }
        }
        $this->loadMore();
    }

    public function refreshRecipes()
    {
        $this->recipes = [];
        $this->page = 1;
        $this->hasMore = true;
        $this->loadMore();
    }

    public function loadMore()
    {
        Log::debug('AvailableRecipesTable loadMore', ['bookId' => $this->bookId]);
        if ($this->loading || !$this->hasMore) return;
        $this->loading = true;
        if (!$this->cookButlerService) {
            $this->cookButlerService = app(\App\Services\CookButlerService::class);
        }
        $patient = $this->getBookPatient();
        Log::debug('AvailableRecipesTable loadMore patient', ['patient' => $patient ? $patient->id : null]);
        $favorites = $patient ? array_map('strval', ($patient['settings']['favorites'] ?? [])) : [];
        $book = \App\Models\Book::find($this->bookId);
        $bookRecipeIds = [];
        $bookRecipeExternalIds = [];
        if ($book) {
            $bookRecipeIds = $book->recipes()->pluck('id_recipe')->map('strval')->all();
            $bookRecipeExternalIds = $book->recipes()->pluck('id_external')->map('strval')->all();
        }
        $filters = $this->getFilters();
        // Always count both recipes in DB, favorites, and those shown in availables for offset
        $dbCount = $book ? $book->recipes()->count() : 0;
        $favCount = $patient ? count($patient->settings['favorites'] ?? []) : 0;
        $availCount = count($this->recipes);
        $this->filterOffset = $dbCount + $favCount + $availCount;
        $filters['offset'] = $this->filterOffset;
        $filters['randomize_offset'] = false;
        $result = $this->cookButlerService->fetchAvailableRecipesForPatient($patient, $filters, $this->perPage, $this->filterOffset);
        $recipeIds = $result['recipe_ids'] ?? [];
        $total = $result['total']['value'] ?? 0;
        Log::debug('AvailableRecipesTable loadMore result', ['recipeIds' => $recipeIds, 'total' => $total]);
        if (empty($recipeIds)) {
            $this->hasMore = false;
            $this->loading = false;
            return;
        }
        $recipes = $result['recipes'] ?? [];
        Log::debug('AvailableRecipesTable loadMore recipes', ['count' => count($recipes)]);
        $filtered = array_filter($recipes, function ($recipe) use ($favorites, $bookRecipeIds, $bookRecipeExternalIds) {
            $ids = [];
            if (is_array($recipe)) {
                foreach (['id', 'id_external', 'id_recipe'] as $key) {
                    if (!empty($recipe[$key])) $ids[] = (string)$recipe[$key];
                }
            } elseif (is_object($recipe)) {
                foreach (['id', 'id_external', 'id_recipe'] as $key) {
                    if (!empty($recipe->$key)) $ids[] = (string)$recipe->$key;
                }
            }
            $exclusionList = array_merge($favorites, $bookRecipeIds, $bookRecipeExternalIds);
            foreach ($ids as $id) {
                if (in_array($id, $exclusionList, true)) {
                    return false;
                }
            }
            return true;
        });
        $idsToFetch = [];
        foreach ($filtered as $recipe) {
            $allergens = is_array($recipe) ? ($recipe['allergens'] ?? null) : (is_object($recipe) ? ($recipe['allergens'] ?? null) : null);
            $diets = is_array($recipe) ? ($recipe['diets'] ?? null) : (is_object($recipe) ? ($recipe['diets'] ?? null) : null);
            $id = is_array($recipe)
                ? ($recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] ?? null)
                : (is_object($recipe) ? ($recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] ?? null) : null);
            if (empty($allergens) || empty($diets)) {
                $idsToFetch[] = $id;
            }
        }
        $detailsMap = [];
        if (!empty($idsToFetch)) {
            if (!$this->cookButlerService) {
                $this->cookButlerService = app(\App\Services\CookButlerService::class);
            }
            $details = $this->cookButlerService->fetchRecipeDetailsBatch($idsToFetch, $patient);
            foreach ($details as $detail) {
                $detailsMap[$detail['id']] = $detail;
            }
        }
        $normalized = array_map(function ($recipe) use ($detailsMap) {
            $id = is_array($recipe)
                ? ($recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] ?? null)
                : (is_object($recipe) ? ($recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] ?? null) : null);
            if ($id && isset($detailsMap[$id])) {
                $detail = $detailsMap[$id];
                $allergens = is_array($recipe) ? ($recipe['allergens'] ?? null) : (is_object($recipe) ? ($recipe['allergens'] ?? null) : null);
                $diets = is_array($recipe) ? ($recipe['diets'] ?? null) : (is_object($recipe) ? ($recipe['diets'] ?? null) : null);
                if (empty($allergens) && !empty($detail['allergens'])) {
                    if (is_array($recipe)) {
                        $recipe['allergens'] = $detail['allergens'];
                    } elseif (is_object($recipe)) {
                        $recipe['allergens'] = $detail['allergens'];
                    }
                }
                if (empty($diets) && !empty($detail['diets'])) {
                    if (is_array($recipe)) {
                        $recipe['diets'] = $detail['diets'];
                    } elseif (is_object($recipe)) {
                        $recipe['diets'] = $detail['diets'];
                    }
                }
            }
            return self::normalizeRecipe(is_array($recipe) ? $recipe : (array)$recipe);
        }, $filtered);
        $existingIds = array_map(function($r) {
            return is_array($r)
                ? ($r['id'] ?? $r['id_external'] ?? $r['id_recipe'] ?? null)
                : (is_object($r) ? ($r['id'] ?? $r['id_external'] ?? $r['id_recipe'] ?? null) : null);
        }, $this->recipes);
        $uniqueNormalized = array_filter($normalized, function($r) use ($existingIds) {
            $id = is_array($r)
                ? ($r['id'] ?? $r['id_external'] ?? $r['id_recipe'] ?? null)
                : (is_object($r) ? ($r['id'] ?? $r['id_external'] ?? $r['id_recipe'] ?? null) : null);
            return $id && !in_array($id, $existingIds);
        });
        $this->recipes = array_values(array_merge($this->recipes, $uniqueNormalized));
        $this->refreshKey++;
        // Before incrementing filterOffset, ensure it is an int
        $this->filterOffset = (int) $this->filterOffset;
        $this->filterOffset += count($uniqueNormalized);
        $this->page++;
        if (count($this->recipes) >= $total) {
            $this->hasMore = false;
        }
        $this->loading = false;
    }

    /**
     * Normalize a recipe array to always include all relevant fields from API.
     *
     * @param array $recipe
     * @return array
     */
    public static function normalizeRecipe(array $recipe = null): array
    {
        if ($recipe === null) {
            return [];
        }
        $optional = $recipe['optional'] ?? [];
        $main = $recipe['recipe'] ?? [];
        $title = $recipe['title'] ?? $optional['title'] ?? $main['title'] ?? $recipe['name'] ?? '';
        $category = self::normalizeField($recipe['category'] ?? $optional['category'] ?? $main['category'] ?? []);
        $allergens = self::normalizeField($recipe['allergens'] ?? $optional['allergens'] ?? $main['allergens'] ?? []);
        $diets = self::normalizeField($recipe['diets'] ?? $optional['diets'] ?? $main['diets'] ?? []);
        $description = $recipe['description'] ?? $optional['description'] ?? $main['description'] ?? '';
        $images = $recipe['images'] ?? $optional['images'] ?? $main['images'] ?? ($recipe['media']['preview'] ?? ($recipe['media']['search'] ?? []));
        if (is_string($images)) {
            $images = [$images];
        } elseif (!is_array($images)) {
            $images = [];
        }
        $id_recipe = $recipe['id_recipe'] ?? $main['id_recipe'] ?? null;
        if (!$id_recipe && isset($recipe['id_external'])) {
            $id_recipe = \App\Models\Recipe::where('id_external', $recipe['id_external'])->value('id_recipe');
        }
        if (!$id_recipe && isset($recipe['id'])) {
            $id_recipe = \App\Models\Recipe::where('id_external', $recipe['id'])->value('id_recipe');
        }
        // Add all other fields from API
        $fields = [
            'id', 'id_external', 'url', 'media', 'optional', 'recipe', 'category', 'country', 'difficulty', 'time', 'time_total', 'serving', 'yield_quantity_1', 'yield_quantity_2', 'yield_info', 'yield_info_short', 'price', 'substances', 'ingredients', 'steps', 'diets', 'allergens', 'course', 'language', 'create', 'last_update', 'images', 'description', 'title', 'subtitle', 'alttitle'
        ];
        $normalized = [
            'title' => $title,
            'category' => $category,
            'allergens' => $allergens,
            'diets' => $diets,
            'description' => $description,
            'images' => $images,
            'id_recipe' => $id_recipe,
        ];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $normalized) && (isset($recipe[$field]) || isset($optional[$field]) || isset($main[$field]))) {
                $normalized[$field] = $recipe[$field] ?? $optional[$field] ?? $main[$field] ?? null;
            }
        }
        return $normalized;
    }

    public function addToBook($externalId)
    {
        Log::debug('addToBook called', ['externalId' => $externalId, 'bookId' => $this->bookId]);
        $user = Auth::user();
        $book = Book::find($this->bookId);
        if (!$user || !$book || !$book->patient) return;
        if (!$this->cookButlerService) {
            $this->cookButlerService = app(\App\Services\CookButlerService::class);
        }
        // Always use the single recipe fetch
        $recipeData = $this->cookButlerService->fetchRecipeDetails($externalId, $book->patient);
        if (!$recipeData) return;
        $patient = $book->patient;

        // Save recipe if not exists
        $recipe = Recipe::where('id_external', $externalId)->first();
        if (!$recipe) {
            $recipe = Recipe::create([
                'id_external' => $externalId,
                'title' => $recipeData['title'] ?? '',
                'subtitle' => $recipeData['subtitle'] ?? null,
                'description' => $recipeData['description'] ?? null,
                'category' => is_string($recipeData['category'] ?? null) ? $recipeData['category'] : json_encode($recipeData['category'] ?? []),
                'substances' => is_string($recipeData['substances'] ?? null) ? $recipeData['substances'] : json_encode($recipeData['substances'] ?? []),
                'media' => is_string($recipeData['media'] ?? null) ? $recipeData['media'] : json_encode($recipeData['media'] ?? []),
                'images' => is_string($recipeData['images'] ?? null) ? $recipeData['images'] : json_encode($recipeData['images'] ?? []),
                'serving' => $recipeData['serving'] ?? null,
                'language' => 'de-de',
                'difficulty' => $recipeData['difficulty'] ?? null,
                'time' => is_string($recipeData['time'] ?? null) ? $recipeData['time'] : json_encode($recipeData['time'] ?? 'keine Angabe'),
                'steps' => is_string($recipeData['steps'] ?? null) ? $recipeData['steps'] : json_encode($recipeData['steps'] ?? []),
                'ingredients' => is_string($recipeData['ingredients'] ?? null) ? $recipeData['ingredients'] : json_encode($recipeData['ingredients'] ?? []),
                'diets' => is_string($recipeData['diets'] ?? null) ? $recipeData['diets'] : json_encode($recipeData['diets'] ?? []),
                'course' => !empty($recipeData['category']) ? \App\Filament\Resources\BookResource::mapCategoryToCourse(
                    \App\Filament\Resources\BookResource::getPrimaryCategory(
                        is_string($recipeData['category']) ? json_decode($recipeData['category'], true) : $recipeData['category']
                    )
                ) : 'main_course',
                'yield_quantity_1' => $recipeData['yield_quantity_1'] ?? null,
                'yield_quantity_2' => $recipeData['yield_quantity_2'] ?? null,
                'yield_info' => $recipeData['yield_info'] ?? null,
                'yield_info_short' => $recipeData['yield_info_short'] ?? null,
                'price' => $recipeData['price'] ?? null,
                'suitable_for_pregnancy' => $recipeData['suitable_for_pregnancy'] ?? null,
                'alttitle' => $recipeData['alttitle'] ?? null,
                'allergens' => is_string($recipeData['allergens'] ?? null) ? $recipeData['allergens'] : json_encode($recipeData['allergens'] ?? []),
                'create' => $recipeData['create'] ?? null,
                'last_update' => $recipeData['last_update'] ?? null,
            ]);
        }
        // Add to book
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
        // Remove from available recipes
        $recipesArray = $this->recipes;
        if ($recipesArray instanceof \Illuminate\Support\Collection) {
            $recipesArray = $recipesArray->all();
        }
        $recipesArray = array_filter($recipesArray, function ($r) use ($externalId) {
            $id = isset($r['id']) ? $r['id'] : (isset($r['id_external']) ? $r['id_external'] : (isset($r['id_recipe']) ? $r['id_recipe'] : null));
            return $id != $externalId;
        });
        $this->recipes = array_values($recipesArray);
        $this->dispatch('recipeAddedToBook', $externalId);
    }

    public function addToFavorites($externalId)
    {
        Log::debug('addToFavorites called', ['externalId' => $externalId, 'bookId' => $this->bookId]);
        $user = $this->getBookPatient();
        if (!$user) return;
        if (!method_exists($user, 'save')) {
            $user = \App\Models\User::find($user['id']);
            if (!$user) return;
        }
        $settings = $user['settings'] ?? [];
        $favorites = $settings['favorites'] ?? [];
        if (!in_array($externalId, $favorites)) {
            $favorites[] = $externalId;
            $settings['favorites'] = $favorites;
            $user['settings'] = $settings;
            $user->save();
            $this->dispatch('recipeAddedToFavorites', $externalId);
        }
        // Remove from available recipes (UI only)
        $recipesArray = $this->recipes;
        if ($recipesArray instanceof \Illuminate\Support\Collection) {
            $recipesArray = $recipesArray->all();
        }
        $recipesArray = array_filter($recipesArray, function ($r) use ($externalId) {
            $id = isset($r['id']) ? $r['id'] : (isset($r['id_external']) ? $r['id_external'] : (isset($r['id_recipe']) ? $r['id_recipe'] : null));
            return $id != $externalId;
        });
        $this->recipes = array_values($recipesArray);
    }

    public function removeFromFavorites($externalId)
    {
        $user = $this->getBookPatient();
        if (!$user) return;
        if (!method_exists($user, 'save')) {
            $user = \App\Models\User::find($user['id']);
            if (!$user) return;
        }
        $settings = $user['settings'] ?? [];
        $favorites = $settings['favorites'] ?? [];
        $favorites = array_filter($favorites, fn($fav) => $fav != $externalId);
        $settings['favorites'] = array_values($favorites);
        $user['settings'] = $settings;
        $user->save();
        $this->dispatch('recipeRemovedFromFavorites', $externalId);
        // Add back to available recipes if not in book, prepending to the beginning
        $book = Book::find($this->bookId);
        $inBook = false;
        if ($book) {
            $recipe = \App\Models\Recipe::where('id_external', $externalId)->orWhere('id_recipe', $externalId)->first();
            if ($recipe) {
                $inBook = $book->recipes()->where('id_recipe', $recipe->id_recipe)->exists();
                if (!$inBook) {
                    $arr = $recipe->toArray();
                    foreach (['category', 'allergens', 'diets'] as $field) {
                        if (isset($arr[$field])) {
                            $decoded = self::normalizeField($arr[$field] ?? null);
                            $arr[$field] = $decoded;
                        }
                    }
                    // Always fetch latest details from API for images when moving to availables
                    if (!empty($arr['id_external'])) {
                        if (!$this->cookButlerService) {
                            $this->cookButlerService = app(\App\Services\CookButlerService::class);
                        }
                        $apiRecipe = $this->cookButlerService->fetchRecipeDetails($arr['id_external'], $book->patient);
                        if (!empty($apiRecipe['images'])) {
                            $arr['images'] = $apiRecipe['images'];
                        } elseif (!empty($apiRecipe['media']['preview'])) {
                            $arr['images'] = is_array($apiRecipe['media']['preview']) ? $apiRecipe['media']['preview'] : [$apiRecipe['media']['preview']];
                        }
                        if (!empty($apiRecipe['media'])) {
                            $arr['media'] = $apiRecipe['media'];
                        }
                    }
                    $recipesArray = $this->recipes;
                    if ($recipesArray instanceof \Illuminate\Support\Collection) {
                        $recipesArray = $recipesArray->all();
                    }
                    array_unshift($recipesArray, self::normalizeRecipe($arr));
                    $this->recipes = array_values($recipesArray);
                }
            }
        }
    }

    public function addToAvailableRecipes($id)
    {
        // Only add if not already present
        $alreadyPresent = array_filter($this->recipes, function ($r) use ($id) {
            $rid = isset($r['id']) ? $r['id'] : (isset($r['id_external']) ? $r['id_external'] : (isset($r['id_recipe']) ? $r['id_recipe'] : null));
            return $rid == $id;
        });
        if ($alreadyPresent) return;
        $recipe = \App\Models\Recipe::find($id);
        if ($recipe) {
            $arr = $recipe->toArray();
            foreach (['category', 'allergens', 'diets'] as $field) {
                if (isset($arr[$field])) {
                    $decoded = self::normalizeField($arr[$field] ?? null);
                    $arr[$field] = $decoded;
                }
            }
            $this->recipes[] = self::normalizeRecipe($arr);
        }
    }

    public function updatedFilterTitle() { $this->resetAndReload(); }
    public function updatedFilterDifficulty() { $this->resetAndReload(); }
    public function updatedFilterCourse() { $this->resetAndReload(); }
    public function updatedFilterIngredients() { $this->resetAndReload(); }
    public function updatedFilterDiets() { $this->resetAndReload(); }

    protected function getFilters()
    {
        $filters = [];
        if ($this->filterTitle) $filters['title'] = $this->filterTitle;
        // Prepend ingredients keywords to the query string, not as a filter
        $ingredientQuery = '';
        if (!empty($this->filterIngredients)) {
            // Replace commas and multiple spaces with a single space
            $ingredientQuery = preg_replace('/[\s,]+/', ' ', $this->filterIngredients);
            // Convert / to || for OR, and - to -- for NOT (CookButler API expects -- and ||)
            $ingredientQuery = preg_replace('/\s*\/\s*/', ' || ', $ingredientQuery); // OR
            $ingredientQuery = preg_replace('/\s*-([\wäöüÄÖÜß]+)/u', ' -- $1', $ingredientQuery); // NOT
            $ingredientQuery = trim($ingredientQuery);
        }
        // Difficulty
        if (is_array($this->filterDifficulty) && !empty($this->filterDifficulty)) {
            $selectedDifficulties = array_keys(array_filter($this->filterDifficulty));
            if (!empty($selectedDifficulties)) {
                $filters['difficulty'] = $selectedDifficulties;
            }
        }
        // Course
        if (is_array($this->filterCourse) && !empty($this->filterCourse)) {
            $selectedCourses = array_keys(array_filter($this->filterCourse));
            if (!empty($selectedCourses)) {
                $filters['courses'] = $selectedCourses;
            }
        }
        // Diets
        if (is_array($this->filterDiets) && !empty($this->filterDiets)) {
            $selectedDiets = array_keys(array_filter($this->filterDiets));
            if (!empty($selectedDiets)) {
                $filters['diets'] = $selectedDiets;
            }
        }
        // Allergen
        if (is_array($this->filterAllergen) && !empty($this->filterAllergen)) {
            $selectedAllergens = array_keys(array_filter($this->filterAllergen));
            if (!empty($selectedAllergens)) {
                $filters['allergen'] = $selectedAllergens;
            }
        }
        // Category
        if (is_array($this->filterCategory) && !empty($this->filterCategory)) {
            $selectedCategories = array_keys(array_filter($this->filterCategory));
            if (!empty($selectedCategories)) {
                $filters['category'] = $selectedCategories;
            }
        }
        // Country
        if (is_array($this->filterCountry) && !empty($this->filterCountry)) {
            $selectedCountries = $this->filterCountry;
            if (!empty($selectedCountries)) {
                $filters['country'] = $selectedCountries;
            }
        }
        // Max Time
        if (is_array($this->filterMaxTime) && !empty($this->filterMaxTime)) {
            $selectedTimes = array_keys(array_filter($this->filterMaxTime));
            if (!empty($selectedTimes)) {
                $filters['max_time'] = $selectedTimes;
            }
        }
        $filters['offset'] = (int) $this->filterOffset;
        $filters['randomize_offset'] = (bool) $this->filterRandomizeOffset;
        // Compose the q parameter: ingredients keywords + allergen exclusion
        $patient = $this->getBookPatient();
        $allergenQ = $patient ? $this->cookButlerService->buildSearchQuery($patient) : '';
        $filters['q'] = trim(($ingredientQuery ? $ingredientQuery . ' ' : '') . $allergenQ);
        \Illuminate\Support\Facades\Log::debug('AvailableRecipesTable getFilters', ['filterIngredients' => $this->filterIngredients, 'q' => $filters['q']]);
        return $filters;
    }

    public function resetAndReload()
    {
        $this->recipes = [];
        $this->page = 1;
        $this->hasMore = true;
        $this->loadMore();
    }

    public function openRecipeModal(...$args)
    {
        $id = $args[0] ?? null;
        if (!$id) return;
        $this->modalRecipeId = $id;
        $recipe = \App\Models\Recipe::find($id);
        if (!$recipe) {
            // Fallback: fetch from API
            if (!$this->cookButlerService) {
                $this->cookButlerService = app(\App\Services\CookButlerService::class);
            }
            // Try to find external id
            $externalId = null;
            $found = collect($this->recipes)->first(function ($r) use ($id) {
                return (isset($r['id_recipe']) ? $r['id_recipe'] : (isset($r['id']) ? $r['id'] : (isset($r['id_external']) ? $r['id_external'] : null))) == $id;
            });
            if ($found) {
                $externalId = $found['id_external'] ?? $found['id'] ?? $found['id_recipe'] ?? null;
            } else {
                $externalId = $id;
            }
            $apiRecipe = $this->cookButlerService->fetchRecipeDetails($externalId, $this->getBookPatient());
            if ($apiRecipe) {
                $recipe = $apiRecipe;
            }
        }
        $this->modalRecipe = $recipe;
        \Illuminate\Support\Facades\Log::debug('openRecipeModal', ['id' => $id, 'modalRecipe' => $this->modalRecipe]);
        $this->showRecipeModal = true;
    }

    public function closeRecipeModal()
    {
        $this->showRecipeModal = false;
        $this->modalRecipeId = null;
        $this->modalRecipe = null;
    }

    public function render()
    {
        // Debug: log the structure and types of all recipes
        Log::debug('AvailableRecipesTable render: recipes', [
            'types' => array_map(function($r) {
                // Always work with array, never Eloquent object
                if ($r instanceof \App\Models\Recipe) {
                    $arr = $r->getAttributes();
                    foreach (['category', 'diets', 'allergens', 'media', 'ingredients', 'steps', 'substances', 'images', 'time'] as $jsonField) {
                        if (isset($arr[$jsonField])) {
                            $arr[$jsonField] = self::normalizeField($arr[$jsonField]);
                        }
                    }
                    $r = $arr;
                }
                return [
                    'type' => gettype($r),
                    'category_type' => isset($r['category']) ? gettype($r['category']) : null,
                    'diets_type' => isset($r['diets']) ? gettype($r['diets']) : null,
                    'id' => isset($r['id']) ? $r['id'] : (isset($r['id_external']) ? $r['id_external'] : (isset($r['id_recipe']) ? $r['id_recipe'] : null)),
                ];
            }, is_array($this->recipes) ? $this->recipes : $this->recipes->all()),
        ]);
        return view('livewire.available-recipes-table', [
            'recipes' => is_array($this->recipes)
                ? array_map(function($r) {
                    if ($r instanceof \App\Models\Recipe) {
                        $arr = $r->getAttributes();
                        foreach (['category', 'diets', 'allergens', 'media', 'ingredients', 'steps', 'substances', 'images', 'time'] as $jsonField) {
                            if (isset($arr[$jsonField])) {
                                $arr[$jsonField] = self::normalizeField($arr[$jsonField]);
                            }
                        }
                        $r = $arr;
                    }
                    return $r;
                }, $this->recipes)
                : $this->recipes,
            'showActions' => $this->showActions,
            'bookId' => $this->bookId,
            'hasMore' => $this->hasMore,
            'loading' => $this->loading,
            'showRecipeModal' => $this->showRecipeModal,
            'modalRecipe' => $this->modalRecipe,
            'refreshKey' => $this->refreshKey,
        ]);
    }

    public function getCachedSubNavigation(): array
    {
        return [];
    }

    public function getSubNavigation(): array { return []; }
    public function getSubNavigationGroups(): array { return []; }
    public function getSubNavigationPosition(): array { return []; }
    public function getWidgetData(): array { return []; }
    public function getHeader(): array { return []; }
    public function getHeading(): array { return []; }
    public function getVisibleHeaderWidgets(): array { return []; }
    public function getVisibleFooterWidgets(): array { return []; }
    public function getFooter(): array { return []; }
    public function getRenderHookScopes(): array { return []; }
    public function getMountedFormComponentAction() { return null; }

    public function confirmRemoveFromFavorites($externalId)
    {
        // This is only called from JS-confirmed UI, so just call removeFromFavorites
        $this->removeFromFavorites($externalId);
    }

    public function prependAvailableRecipe($externalId)
    {
        $book = Book::find($this->bookId);
        if (!$book) return;
        $recipe = \App\Models\Recipe::where('id_external', $externalId)->orWhere('id_recipe', $externalId)->first();
        if (!$recipe) return;
        // Check if already in book
        $inBook = $book->recipes()->where('id_recipe', $recipe->id_recipe)->exists();
        if ($inBook) return;
        // Check if already in availables
        $alreadyPresent = collect($this->recipes)->contains(function ($r) use ($recipe) {
            $rid = isset($r['id']) ? $r['id'] : (isset($r['id_external']) ? $r['id_external'] : (isset($r['id_recipe']) ? $r['id_recipe'] : null));
            return $rid == $recipe['id_external'] || $rid == $recipe['id_recipe'];
        });
        if ($alreadyPresent) return;
        $arr = $recipe->toArray();
        foreach (['category', 'allergens', 'diets'] as $field) {
            if (isset($arr[$field])) {
                $decoded = self::normalizeField($arr[$field] ?? null);
                $arr[$field] = $decoded;
            }
        }
        // Ensure images is always an array
        if (isset($arr['images'])) {
            if (is_string($arr['images'])) {
                $decodedImages = json_decode($arr['images'], true);
                $arr['images'] = is_array($decodedImages) ? $decodedImages : [];
            } elseif (!is_array($arr['images'])) {
                $arr['images'] = [];
            }
        } else {
            $arr['images'] = [];
        }
        // Only fetch latest details from API for images if truly missing, but cache for 1 day
        $shouldFetchImages = false;
        if (!isset($arr['images']) || (is_array($arr['images']) && count($arr['images']) === 0)) {
            $shouldFetchImages = true;
        } elseif (is_string($arr['images'])) {
            $decoded = json_decode($arr['images'], true);
            if (empty($decoded) || !is_array($decoded)) {
                $shouldFetchImages = true;
            }
        }
        $cacheKey = 'recipe_images_' . $arr['id_external'];
        $cachedImages = Cache::get($cacheKey);
        if (!empty($arr['id_external']) && $shouldFetchImages) {
            if ($cachedImages && is_array($cachedImages) && count($cachedImages) > 0) {
                $arr['images'] = $cachedImages;
                Log::debug('Loaded recipe images from cache', ['id_external' => $arr['id_external']]);
            } else {
                Log::debug('Fetching recipe details from API for images', ['id_external' => $arr['id_external']]);
                if (!$this->cookButlerService) {
                    $this->cookButlerService = app(\App\Services\CookButlerService::class);
                }
                $apiRecipe = $this->cookButlerService->fetchRecipeDetails($arr['id_external'], $book->patient);
                if (!empty($apiRecipe['images'])) {
                    $arr['images'] = $apiRecipe['images'];
                } elseif (!empty($apiRecipe['media']['preview'])) {
                    $arr['images'] = is_array($apiRecipe['media']['preview']) ? $apiRecipe['media']['preview'] : [$apiRecipe['media']['preview']];
                }
                if (!empty($apiRecipe['media'])) {
                    $arr['media'] = $apiRecipe['media'];
                }
                // Cache the images for 1 day
                if (!empty($arr['images'])) {
                    Cache::put($cacheKey, $arr['images'], now()->addDay());
                    Log::debug('Cached recipe images for 1 day', ['id_external' => $arr['id_external']]);
                }
            }
        }
        $recipesArray = $this->recipes;
        if ($recipesArray instanceof \Illuminate\Support\Collection) {
            $recipesArray = $recipesArray->all();
        }
        array_unshift($recipesArray, self::normalizeRecipe($arr));
        $this->recipes = array_values($recipesArray);
    }

    protected function getBookPatient()
    {
        $book = Book::find($this->bookId);
        if (!$book || !$book['patient']) return null;
        $user = $book['patient'];
        if (!is_object($user)) {
            $user = \App\Models\User::find($book['patient_id']);
            if (!is_object($user)) return null;
        }
        return $user;
    }

    public function applyFilters()
    {
        $this->resetAndReload();
    }

    public function saveFilters()
    {
        $user = $this->getBookPatient();
        if (!$user) return;
        $settings = $user['settings'] ?? [];
        $settings['recipe_filter_set'] = [
            'filterTitle' => $this->filterTitle,
            'filterIngredients' => $this->filterIngredients,
            'filterAllergen' => $this->filterAllergen ?? [],
            'filterCategory' => $this->filterCategory ?? [],
            'filterCountry' => $this->filterCountry ?? [],
            'filterCourse' => $this->filterCourse ?? [],
            'filterDiets' => $this->filterDiets ?? [],
            'filterDifficulty' => $this->filterDifficulty ?? [],
            'filterMaxTime' => $this->filterMaxTime ?? [],
        ];
        $user['settings'] = $settings;
        $user->save();
        \Filament\Notifications\Notification::make()
            ->title(__('Filter gespeichert'))
            ->body(__('Das aktuelle Filter-Set wurde im Benutzerprofil gespeichert.'))
            ->success()
            ->send();
    }

    // Utility function for hardening json_decode for all recipe fields
    private static function normalizeField($value) {
        return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
    }

    // Utility: Convert available recipe array to Recipe model (create or fetch)
    public static function arrayToRecipeModel(array $recipe)
    {
        $externalId = $recipe['id'] ?? $recipe['id_external'] ?? null;
        if (!$externalId) return null;
        $model = \App\Models\Recipe::where('id_external', $externalId)->first();
        if (!$model) {
            $optional = $recipe['optional'] ?? [];
            $main = $recipe['recipe'] ?? [];
            // Always prefer images, then media.preview
            $images = $recipe['images'] ?? $optional['images'] ?? $main['images'] ?? null;
            if ((empty($images) || count($images) === 0) && !empty($recipe['media']['preview'])) {
                $images = is_array($recipe['media']['preview']) ? $recipe['media']['preview'] : [$recipe['media']['preview']];
            }
            $model = \App\Models\Recipe::create([
                'id_external' => $externalId,
                'title' => $recipe['title'] ?? $optional['title'] ?? $main['title'] ?? '',
                'subtitle' => $recipe['subtitle'] ?? $optional['subtitle'] ?? $main['subtitle'] ?? null,
                'alttitle' => $recipe['alttitle'] ?? $optional['alttitle'] ?? $main['alttitle'] ?? null,
                'description' => $recipe['description'] ?? $optional['description'] ?? $main['description'] ?? null,
                'category' => is_string($recipe['category'] ?? null) ? $recipe['category'] : json_encode($recipe['category'] ?? $optional['category'] ?? $main['category'] ?? []),
                'country' => is_string($recipe['country'] ?? null) ? $recipe['country'] : json_encode($recipe['country'] ?? $optional['country'] ?? $main['country'] ?? []),
                'difficulty' => $recipe['difficulty'] ?? $optional['difficulty'] ?? $main['difficulty'] ?? null,
                'time' => is_string($recipe['time'] ?? null) ? $recipe['time'] : json_encode($recipe['time'] ?? $optional['time'] ?? $main['time'] ?? []),
                'time_total' => $recipe['time_total'] ?? $optional['time_total'] ?? $main['time_total'] ?? null,
                'serving' => $recipe['serving'] ?? $optional['serving'] ?? $main['serving'] ?? null,
                'yield_quantity_1' => $recipe['yield_quantity_1'] ?? $optional['yield_quantity_1'] ?? $main['yield_quantity_1'] ?? null,
                'yield_quantity_2' => $recipe['yield_quantity_2'] ?? $optional['yield_quantity_2'] ?? $main['yield_quantity_2'] ?? null,
                'yield_info' => $recipe['yield_info'] ?? $optional['yield_info'] ?? $main['yield_info'] ?? null,
                'yield_info_short' => $recipe['yield_info_short'] ?? $optional['yield_info_short'] ?? $main['yield_info_short'] ?? null,
                'price' => $recipe['price'] ?? $optional['price'] ?? $main['price'] ?? null,
                'substances' => is_string($recipe['substances'] ?? null) ? $recipe['substances'] : json_encode($recipe['substances'] ?? $optional['substances'] ?? $main['substances'] ?? []),
                'media' => is_string($recipe['media'] ?? null) ? $recipe['media'] : json_encode($recipe['media'] ?? $optional['media'] ?? $main['media'] ?? []),
                'images' => is_string($images) ? $images : json_encode($images ?? []),
                'ingredients' => is_string($recipe['ingredients'] ?? null) ? $recipe['ingredients'] : json_encode($recipe['ingredients'] ?? $optional['ingredients'] ?? $main['ingredients'] ?? []),
                'steps' => is_string($recipe['steps'] ?? null) ? $recipe['steps'] : json_encode($recipe['steps'] ?? $optional['steps'] ?? $main['steps'] ?? []),
                'diets' => is_string($recipe['diets'] ?? null) ? $recipe['diets'] : json_encode($recipe['diets'] ?? $optional['diets'] ?? $main['diets'] ?? []),
                'allergens' => is_string($recipe['allergens'] ?? null) ? $recipe['allergens'] : json_encode($recipe['allergens'] ?? $optional['allergens'] ?? $main['allergens'] ?? []),
                'course' => $recipe['course'] ?? $optional['course'] ?? $main['course'] ?? null,
                'language' => $recipe['language'] ?? $optional['language'] ?? $main['language'] ?? 'de-de',
                'create' => $recipe['create'] ?? $optional['create'] ?? $main['create'] ?? null,
                'last_update' => $recipe['last_update'] ?? $optional['last_update'] ?? $main['last_update'] ?? null,
                'url' => $recipe['url'] ?? $optional['url'] ?? $main['url'] ?? null,
            ]);
        }
        return $model;
    }

    // Utility: Convert Recipe model to normalized array for availables
    public static function recipeModelToArray($recipe)
    {
        if (!$recipe) return [];
        $arr = is_object($recipe) && method_exists($recipe, 'toArray') ? $recipe->toArray() : (array)$recipe;
        foreach ([
            'category', 'diets', 'allergens', 'media', 'ingredients', 'steps', 'substances', 'images', 'time', 'country'
        ] as $field) {
            if (isset($arr[$field])) {
                $arr[$field] = is_string($arr[$field]) ? json_decode($arr[$field], true) : (is_array($arr[$field]) ? $arr[$field] : []);
            }
        }
        // Always set 'id' to id_external for API compatibility
        if (isset($arr['id_external'])) {
            $arr['id'] = $arr['id_external'];
        }
        // Add url if missing
        if (!isset($arr['url']) && isset($arr['id_external'])) {
            $arr['url'] = 'https://api.cookbutler.com/v1/recipes/recipe/' . $arr['id_external'];
        }
        // Add optional fields as a subarray for compatibility
        $arr['optional'] = [
            'title' => $arr['title'] ?? null,
            'subtitle' => $arr['subtitle'] ?? null,
            'alttitle' => $arr['alttitle'] ?? null,
            'category' => $arr['category'] ?? [],
            'country' => $arr['country'] ?? [],
            'difficulty' => $arr['difficulty'] ?? null,
            'time_total' => $arr['time_total'] ?? null,
            'serving' => $arr['serving'] ?? null,
            'yield_quantity_1' => $arr['yield_quantity_1'] ?? null,
            'yield_quantity_2' => $arr['yield_quantity_2'] ?? null,
            'yield_info' => $arr['yield_info'] ?? null,
            'yield_info_short' => $arr['yield_info_short'] ?? null,
            'price' => $arr['price'] ?? null,
            'substances' => $arr['substances'] ?? [],
            'media' => $arr['media'] ?? [],
            'images' => $arr['images'] ?? [],
            'ingredients' => $arr['ingredients'] ?? [],
            'steps' => $arr['steps'] ?? [],
            'diets' => $arr['diets'] ?? [],
            'allergens' => $arr['allergens'] ?? [],
            'course' => $arr['course'] ?? null,
            'language' => $arr['language'] ?? null,
            'create' => $arr['create'] ?? null,
            'last_update' => $arr['last_update'] ?? null,
            'url' => $arr['url'] ?? null,
        ];
        return $arr;
    }
}
