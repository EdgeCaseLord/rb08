<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CookButlerService
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $searchEndpoint = 'https://api.cookbutler.com/v1/recipes/search';
    protected string $batchEndpoint = 'https://api.cookbutler.com/v1/recipes/recipes-batch';

    private array $dietSlugMap = [
        'biologisch' => 'organic',
        'eifrei' => 'egg-free',
        'glutenfrei' => 'gluten-free',
        'histamin-free' => 'histamine-free',
        'laktosefrei' => 'lactose-free',
        'ohne Fisch' => 'fish-free',
        'ohne Fleisch' => 'meat-free',
        'sojafrei' => 'soy-free',
        'vegan' => 'vegan',
        'vegetarisch' => 'vegetarian',
        'weizenfrei' => 'wheat-free',
        'fruktose' => 'fructose',
        'ohne Fruktose' => 'fructose',
        'alcohol-free' => 'alcohol-free',
    ];

    public function __construct()
    {
        $this->apiKey = config('cookbutler.api_key', '');
        $this->apiSecret = config('cookbutler.api_secret', '');
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::error('CookButler API-Zugangsdaten nicht konfiguriert');
        }
    }

    public function generateJwt(): string
    {
        $iatTime = (new \DateTime('now', new \DateTimeZone('UTC')))->getTimestamp();
        $expTime = $iatTime + 3600;
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payload = ['iat' => $iatTime, 'exp' => $expTime, 'api_key' => $this->apiKey];
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->base64UrlEncode(hash_hmac('sha256', $headerEncoded . '.' . $payloadEncoded, $this->apiSecret, true));
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }

    private function base64UrlEncode(string $data): string
    {
        $base64 = base64_encode($data);
        return str_replace(['+', '/', '='], ['-', '_', ''], $base64);
    }

    // LEGACY: Commented out legacy ingredient-based search query
    // public function buildSearchQuery(User $patient): string
    // {
    //     $allergenIds = $patient->allergens()->pluck('allergens.id')->toArray();
    //     $unsafeIngredients = DB::table('allergen_ingredient')
    //         ->whereIn('allergen_id', $allergenIds)
    //         ->join('ingredients', 'allergen_ingredient.ingredient_id', '=', 'ingredients.id')
    //         ->pluck('ingredients.name_de')
    //         ->unique()
    //         ->toArray();
    //
    //     return empty($unsafeIngredients) ? '' : '-- ' . implode(' -- ', array_map(fn($ing) => "'$ing'", $unsafeIngredients));
    // }

    // NEW: Build search query using allergen codes as in the new matching table
    // Supports both -- and - for NOT, || and / for OR (normalized in AvailableRecipesTable)
    public function buildSearchQuery(array $filters = []): string
    {
        $qParts = [];
        if (!empty($filters['title'])) {
            $qParts[] = $filters['title'];
        }
        $ingredientQuery = '';
        if (!empty($filters['ingredients'])) {
            $ingredientQuery = preg_replace('/[\s,]+/', ' ', $filters['ingredients']);
            $ingredientQuery = preg_replace('/\s*\/\s*/', ' || ', $ingredientQuery); // OR
            $ingredientQuery = preg_replace('/\s*-([\wäöüÄÖÜß]+)/u', ' -- $1', $ingredientQuery); // NOT
            $ingredientQuery = trim($ingredientQuery);
        }
        if (!empty($ingredientQuery)) {
            $qParts[] = $ingredientQuery;
        }
        return trim(implode(' ', $qParts));
    }

    // Helper to get patient allergen codes for API filter
    public function getPatientAllergenCodes(User $patient): array
    {
        $allergens = $patient->allergens()->wherePivot('user_id', $patient->id)->get();
        return $allergens->map(function ($allergen) {
            $name = strtolower($allergen->name);
            $name = str_replace([' ', ',', '-', '(', ')', '*', "'", "."], ['_', '', '', '', '', '', '', ''], $name);
            return 'pro_' . $name;
        })->unique()->toArray();
    }

    /**
     * Merge patient allergens and filter allergens for API filter (deduplicated).
     *
     * @param User $patient
     * @param array $filterAllergens
     * @return array
     */
    public function mergeAllergensForApiFilter(User $patient, array $filterAllergens = []): array
    {
        $patientAllergens = $this->getPatientAllergenCodes($patient);
        return array_unique(array_merge($patientAllergens, $filterAllergens));
    }

    private function mapDietFilters($diets) {
        if (!is_array($diets)) return $diets;
        return array_map(function($diet) {
            if ($diet === 'ohne Fruktose') return $this->dietSlugMap['ohne Fruktose'];
            if ($diet === 'fruktose') return $this->dietSlugMap['fruktose'];
            return $this->dietSlugMap[$diet] ?? $diet;
        }, $diets);
    }

    private function normalizeFilterArray($arr) {
        if (is_array($arr) && array_values($arr) === $arr) {
            // Numeric array, treat as values
            return array_values(array_filter($arr, fn($v) => $v !== false && $v !== null && $v !== ''));
        } elseif (is_array($arr)) {
            // Associative array (key => true/false)
            return array_keys(array_filter($arr));
        }
        return $arr;
    }

    protected function makeApiRequest(string $method, string $url, array $data = [], ?User $patient = null, array $filterAllergens = []): ?array
    {
        $jwt = $this->generateJwt();
        $headers = [
            'Authorization' => "Bearer $jwt",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Always ensure allergens are included in every API request if patient is provided
        if ($patient) {
            $mergedAllergens = $this->mergeAllergensForApiFilter($patient, $filterAllergens);
            if (!isset($data['filters'])) {
                $data['filters'] = [];
            }
            $data['filters']['allergen'] = $mergedAllergens;
        }

        Log::debug('CookButler API request debug', [
            'jwt' => $jwt,
            'headers' => $headers,
            'url' => $url,
            'data' => $data,
        ]);

        Log::debug('Sende CookButler API-Anfrage', ['url' => $url, 'data' => $data]);
        $response = Http::withHeaders($headers)->post($url, $data);

        Log::debug('CookButler API response', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if (!$response->successful()) {
            Log::error('Fehler beim Abrufen von CookButler API-Daten', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $responseData = $response->json();
        return $responseData['data'] ?? [];
    }

    /**
     * Hole Rezepte für einen Patienten nach Gang mit Ausschluss von Allergenen.
     *
     * @param User $patient Der Patient
     * @param string $course Der Gang (starter, main, dessert)
     * @param int $limit Anzahl der Rezepte
     * @param int $offset Zufälliger Offset
     * @return array Array von Rezept-IDs
     */
    public function fetchRecipesForPatientByCourse(User $patient, string $course, int $limit = 5, int $offset = 0): array
    {
        try {
            // If patient has recipe_totals for this course, randomize offset unless offset is explicitly set
            $recipeTotals = $patient->recipe_totals[$course] ?? 0;
            if ($recipeTotals > 0 && $offset === 0) {
                $maxOffset = max(0, $recipeTotals - $limit);
                if ($maxOffset > 0) {
                    $offset = random_int(0, $maxOffset);
                }
            }
            // Merge patient settings for all relevant filters
            $prefs = $this->getUserFilterPreferences($patient);
            $map = [
                'filterTitle' => 'title',
                'filterIngredients' => 'ingredients',
                'filterAllergen' => 'allergen',
                'filterCategory' => 'category',
                'filterCountry' => 'country',
                'filterCourse' => 'courses',
                'filterDiets' => 'diets',
                'filterDifficulty' => 'difficulty',
                'filterMaxTime' => 'max_time',
            ];
            $merged = [];
            foreach ($map as $from => $to) {
                if (isset($prefs[$from]) && $prefs[$from] !== '' && $prefs[$from] !== [] && $prefs[$from] !== null) {
                    $merged[$to] = $prefs[$from];
                }
            }
            // Always merge patient allergens (pro_*) into the allergen filter
            $patientAllergens = $this->getPatientAllergenCodes($patient);
            $filterAllergens = [];
            if (isset($merged['allergen']) && is_array($merged['allergen'])) {
                $filterAllergens = $merged['allergen'];
            }
            $allAllergens = array_unique(array_merge($patientAllergens, $filterAllergens));
            if (!empty($allAllergens)) {
                $merged['allergen'] = $allAllergens;
            }
            // Always set the course filter
            $merged['courses'] = [$course];
            // Build q from title and ingredients only
            $q = $this->buildSearchQuery($merged);
            // Normalize all filter arrays before mapping diets and building $apiFilters
            foreach (['diets','allergen','category','country','courses','difficulty','max_time'] as $filterKey) {
                if (!empty($merged[$filterKey])) {
                    $merged[$filterKey] = $this->normalizeFilterArray($merged[$filterKey]);
                }
            }
            // After normalization, add this before mapping diets:
            if (!empty($merged['diets'])) {
                // Force diets to be an array of string keys
                if (!is_array($merged['diets']) || array_values($merged['diets']) !== $merged['diets']) {
                    $merged['diets'] = array_keys(array_filter($merged['diets']));
                }
                // Remove any numeric indices
                $merged['diets'] = array_filter($merged['diets'], fn($v) => is_string($v) && $v !== '' && !is_numeric($v));
                Log::debug('CookButlerService: diets after normalization', ['diets' => $merged['diets']]);
            }
            $searchData = [
                'language' => 'de-de',
                'searchtype' => 'extended',
                'add_info' => ['title', 'difficulty', 'time_total', 'category', 'substances', 'country', 'serving', 'diets'],
                'filters' => [],
                'q' => $q,
                'limit' => min($limit, 100),
                'offset' => $offset,
            ];
            $apiFilters = [];
            if (!empty($merged['courses'])) {
                $apiFilters['course'] = (array)$merged['courses'];
            }
            if (!empty($merged['difficulty'])) {
                $apiFilters['difficulty'] = (array)$merged['difficulty'];
            }
            if (!empty($merged['diets'])) {
                $merged['diets'] = $this->mapDietFilters($merged['diets']);
                $apiFilters['diet'] = (array)$merged['diets'];
            }
            if (!empty($merged['allergen'])) {
                $apiFilters['allergen'] = (array)$merged['allergen'];
            }
            if (!empty($merged['category'])) {
                $apiFilters['category'] = (array)$merged['category'];
            }
            if (!empty($merged['country'])) {
                $apiFilters['country'] = (array)$merged['country'];
            }
            if (!empty($merged['max_time'])) {
                $apiFilters['max_time'] = (array)$merged['max_time'];
            }
            if (!empty($apiFilters)) {
                $searchData['filters'] = $apiFilters;
            }
            Log::debug('CookButler search request', [
                'patient_id' => $patient->id,
                'course' => $course,
                'filters' => $merged,
                'query' => $q
            ]);
            $data = $this->makeApiRequest('POST', $this->searchEndpoint, $searchData, $patient, !empty($apiFilters['allergen']) ? (array)$apiFilters['allergen'] : []);
            $recipes = $data['recipes'] ?? [];
            if (empty($recipes)) {
                Log::info('Keine Rezepte von der Such-API zurückgegeben', [
                    'patient_id' => $patient->id,
                    'gang' => $course,
                    'filters' => $merged,
                    'query' => $q,
                ]);
                return ['recipe_ids' => [], 'total' => ['value' => 0]];
            }
            return [
                'recipe_ids' => array_column($recipes, 'id'),
                'total' => $data['total'] ?? ['value' => 0],
            ];
        } catch (\Exception $e) {
            Log::error('Fehler beim Abrufen von Rezepten', [
                'patient_id' => $patient->id,
                'course' => $course,
                'error' => $e->getMessage(),
            ]);
            return ['recipe_ids' => [], 'total' => ['value' => 0]];
        }
    }

    /**
     * Hole Rezeptdetails für alle Rezepte in einem Batch-Aufruf.
     *
     * @param array $recipeIds Array von Rezept-IDs
     * @return array Rezeptdetails
     */
    public function fetchRecipeDetailsBatch(array $recipeIds, User $patient = null): array
    {
        try {
            $batchData = [
                'language' => 'de-de',
                'id_list' => $recipeIds,
                'fields' => ['ingredients', 'steps', 'time', 'category', 'substances', 'media', 'images', 'difficulty', 'serving', 'diets', 'allergens'],
            ];
            $data = $this->makeApiRequest('POST', $this->batchEndpoint, $batchData);
            if (empty($data)) {
                Log::info('Keine Rezeptdetails von der Batch-API zurückgegeben', ['rezept_ids' => $recipeIds]);
                return [];
            }

            $recipeDetails = [];
            foreach ($data as $recipeId => $recipeData) {
                $recipeDetail = $recipeData['recipe'] ?? [];
                // Merge all top-level fields and 'recipe' fields
                $merged = array_merge(
                    ['id' => $recipeId, 'title' => $recipeDetail['title'] ?? "Rezept $recipeId"],
                    $recipeDetail,
                    $recipeData // this will include media, images, allergens, etc.
                );
                $recipeDetails[] = $merged;

                Recipe::updateOrCreate(
                    ['id_external' => $recipeId],
                    [
                        'title' => $recipeDetail['title'] ?? "Rezept $recipeId",
                        'subtitle' => $recipeDetail['subtitle'] ?? null,
                        'description' => $recipeDetail['description'] ?? null,
                        'category' => json_encode($recipeData['category'] ?? []),
                        'substances' => json_encode($recipeData['substances'] ?? []),
                        'media' => json_encode($recipeData['media'] ?? []),
                        'images' => json_encode($recipeData['images'] ?? []),
                        'serving' => $recipeDetail['serving'] ?? null,
                        'language' => 'de-de',
                        'difficulty' => $recipeDetail['difficulty'] ?? null,
                        'time' => json_encode($recipeData['time'] ?? 'keine Angabe'),
                        'steps' => json_encode($recipeData['steps'] ?? []),
                        'ingredients' => json_encode($recipeData['ingredients'] ?? []),
                        'diets' => json_encode($recipeData['diets'] ?? $recipeDetail['diets'] ?? []),
                        'course' => !empty($recipeData['category']) ? \App\Filament\Resources\BookResource::mapCategoryToCourse(
                            \App\Filament\Resources\BookResource::getPrimaryCategory($recipeData['category'])
                        ) : 'main_course',
                        'yield_quantity_1' => $recipeDetail['yield_quantity_1'] ?? null,
                        'yield_quantity_2' => $recipeDetail['yield_quantity_2'] ?? null,
                        'yield_info' => $recipeDetail['yield_info'] ?? null,
                        'yield_info_short' => $recipeDetail['yield_info_short'] ?? null,
                        'price' => $recipeDetail['price'] ?? null,
                        'suitable_for_pregnancy' => $recipeDetail['suitable_for_pregnancy'] ?? null,
                        'alttitle' => $recipeDetail['alttitle'] ?? null,
                        'allergens' => json_encode($recipeData['allergens'] ?? []),
                        'create' => $recipeDetail['create'] ?? null,
                        'last_update' => $recipeDetail['last_update'] ?? null,
                    ]
                );

                Log::debug('Stored recipe with diets', [
                    'recipe_id' => $recipeId,
                    'diets' => $recipeData['diets'] ?? $recipeDetail['diets'] ?? []
                ]);
            }

            return $recipeDetails;
        } catch (\Exception $e) {
            Log::error('Fehler beim Abrufen von Rezeptdetails', [
                'recipe_ids' => $recipeIds,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Fetch arbitrary available recipes for a patient, excluding allergens, with optional filters.
     *
     * @param User $patient
     * @param array $filters (keys: courses, title, difficulty, diets_positive, diets_negative, ingredients)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function fetchAvailableRecipesForPatient(User $patient, array $filters = [], int $limit = 10, int $offset = 0): array
    {
        // Merge patient settings with form filters, form filters take precedence
        $prefs = $this->getUserFilterPreferences($patient);
        // Map UI keys to API keys
        $map = [
            'filterTitle' => 'title',
            'filterIngredients' => 'ingredients',
            'filterAllergen' => 'allergen',
            'filterCategory' => 'category',
            'filterCountry' => 'country',
            'filterCourse' => 'courses',
            'filterDiets' => 'diets',
            'filterDifficulty' => 'difficulty',
            'filterMaxTime' => 'max_time',
        ];
        $merged = [];
        foreach ($map as $from => $to) {
            if (isset($prefs[$from]) && $prefs[$from] !== '' && $prefs[$from] !== [] && $prefs[$from] !== null) {
                $merged[$to] = $prefs[$from];
            }
        }
        foreach ($map as $from => $to) {
            $val = $filters[$from] ?? null;
            if (is_array($val)) {
                // If associative (checkbox style), convert to keys; if already list of values, keep as-is
                if (array_values($val) !== $val) {
                    $val = array_keys(array_filter($val));
                } else {
                    $val = array_values(array_filter($val, fn($v) => $v !== false && $v !== null && $v !== ''));
                }
            }
            if ($val !== '' && $val !== [] && $val !== null) {
                $merged[$to] = $val;
            }
        }
        // Allergen logic: always combine patient allergens (pro_*) and filter allergens
        $patientAllergens = $this->getPatientAllergenCodes($patient);
        $filterAllergens = [];
        if (isset($merged['allergen']) && is_array($merged['allergen'])) {
            $filterAllergens = $merged['allergen'];
        }
        $allAllergens = array_unique(array_merge($patientAllergens, $filterAllergens));
        if (!empty($allAllergens)) {
            $merged['allergen'] = $allAllergens;
        }
        // Handle offset and randomize_offset separately
        $apiOffset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
        $randomizeOffset = isset($filters['randomize_offset']) ? (bool) $filters['randomize_offset'] : false;
        if ($randomizeOffset) {
            // If randomize_offset is true, randomize the offset up to a reasonable max (e.g., 1000)
            $apiOffset = random_int(0, 1000);
        }
        Log::info('fetchAvailableRecipesForPatient called', [
            'patient_id' => $patient->id,
            'filters' => $merged,
            'limit' => $limit,
            'offset' => $apiOffset,
            'randomize_offset' => $randomizeOffset,
        ]);
        try {
            // Log raw filters and merged filters for debugging
            Log::debug('CookButlerService: raw filters received', ['filters' => $filters]);
            Log::debug('CookButlerService: merged filters after merging', ['merged' => $merged]);
            // Normalize all filter arrays before mapping diets and building $apiFilters
            foreach (['diets','allergen','category','country','courses','difficulty','max_time'] as $filterKey) {
                if (!empty($merged[$filterKey])) {
                    $merged[$filterKey] = $this->normalizeFilterArray($merged[$filterKey]);
                }
            }
            // After normalization, add this before mapping diets:
            if (!empty($merged['diets'])) {
                // If diets is a numeric array, map indices to keys
                if (is_array($merged['diets']) && isset($merged['diets'][0]) && is_numeric($merged['diets'][0])) {
                    $merged['diets'] = array_map(fn($i) => $this->dietIndexToKey[$i] ?? $i, $merged['diets']);
                }
                // Remove any numeric indices (should now be string keys)
                $merged['diets'] = array_filter($merged['diets'], fn($v) => is_string($v) && $v !== '' && !is_numeric($v));
                Log::debug('CookButlerService: diets after index-to-key normalization', ['diets' => $merged['diets']]);
            }
            $searchData = [
                'language' => 'de-de',
                'searchtype' => 'extended',
                'add_info' => ['title', 'difficulty', 'time_total', 'category', 'substances', 'country', 'serving', 'diets', 'media', 'images', 'allergens', 'description'],
                'limit' => min($limit, 100),
                'offset' => $apiOffset,
            ];
            $apiFilters = [];
            // Map all supported filters to API filters array
            if (!empty($merged['courses'])) {
                $apiFilters['course'] = (array)$merged['courses'];
            }
            if (!empty($merged['difficulty'])) {
                $apiFilters['difficulty'] = (array)$merged['difficulty'];
            }
            if (!empty($merged['diets'])) {
                $merged['diets'] = $this->mapDietFilters($merged['diets']);
                $apiFilters['diet'] = (array)$merged['diets'];
            }
            if (!empty($merged['allergen'])) {
                $apiFilters['allergen'] = (array)$merged['allergen'];
            }
            if (!empty($merged['category'])) {
                $apiFilters['category'] = (array)$merged['category'];
            }
            if (!empty($merged['country'])) {
                $apiFilters['country'] = (array)$merged['country'];
            }
            if (!empty($merged['max_time'])) {
                $apiFilters['max_time'] = (array)$merged['max_time'];
            }
            // Build q from title and ingredients only (string replacements handled in buildSearchQuery)
            $q = $this->buildSearchQuery($merged);
            if (!empty($q)) {
                $searchData['q'] = $q;
            }
            if (!empty($apiFilters)) {
                $searchData['filters'] = $apiFilters;
            }
            // Warn if any filter is not mapped (do not treat offset/randomize_offset as filters)
            $handled = ['courses','difficulty','ingredients','diets','allergen','category','country','max_time','title','q'];
            foreach ($merged as $key => $val) {
                if (!in_array($key, $handled)) {
                    Log::warning('CookButlerService: Unhandled filter key in fetchAvailableRecipesForPatient', ['key' => $key, 'value' => $val]);
                }
            }

            Log::debug('CookButlerService: searchData payload before API request', ['searchData' => $searchData]);

            Log::info('CookButler available recipes search - about to make API request', [
                'patient_id' => $patient->id,
                'filters' => $merged,
                'apiFilters' => $apiFilters,
                'searchData' => $searchData,
            ]);

            $data = $this->makeApiRequest('POST', $this->searchEndpoint, $searchData, $patient, !empty($apiFilters['allergen']) ? (array)$apiFilters['allergen'] : []);
            Log::info('CookButler available recipes search - API response', [
                'patient_id' => $patient->id,
                'response_data' => $data,
            ]);
            $recipes = $data['recipes'] ?? [];
            $total = $data['total']['value'] ?? 0;

            // Count number of recipes for each known course
            $starter = 0;
            $main_course = 0;
            $dessert = 0;
            foreach ($recipes as $recipe) {
                // Get category from 'category' or 'optional.category'
                $categories = [];
                if (!empty($recipe['category'])) {
                    $categories = $recipe['category'];
                } elseif (!empty($recipe['optional']['category'])) {
                    $categories = $recipe['optional']['category'];
                }
                // Normalize to array of values
                if (is_string($categories)) {
                    $categories = [$categories];
                } elseif (is_object($categories)) {
                    $categories = (array)$categories;
                }
                $catVals = array_map('strtolower', array_values($categories));
                Log::debug('Recipe category values', ['catVals' => $catVals, 'recipe' => $recipe]);
                if (in_array('vorspeise', $catVals)) {
                    $starter++;
                }
                if (in_array('hauptgericht', $catVals)) {
                    $main_course++;
                }
                if (in_array('dessert', $catVals)) {
                    $dessert++;
                }
            }
            // \Illuminate\Support\Facades\Log::debug('Course counts (filtered, not stored)', ['starter' => $starter, 'main_course' => $main_course, 'dessert' => $dessert]);

            // Do NOT update $patient->recipe_totals here. This value should only be set by the analysis import (AssignRecipesJob).
            // if ($patient) {
            //     $recipeTotals = $patient->recipe_totals ?? [];
            //     $recipeTotals['starter'] = $starter;
            //     $recipeTotals['main_course'] = $main_course;
            //     $recipeTotals['dessert'] = $dessert;
            //     $patient->recipe_totals = $recipeTotals;
            //     $patient->save();
            // }

            if (empty($recipes)) {
                Log::warning('Keine verfügbaren Rezepte von der Such-API zurückgegeben', [
                    'patient_id' => $patient->id,
                    'filters' => $merged,
                    'query' => $searchData['q'] ?? '',
                    'api_response' => $data,
                ]);
                return ['recipe_ids' => [], 'total' => ['value' => 0]];
            }

            return [
                'recipe_ids' => array_column($recipes, 'id'),
                'total' => $data['total'] ?? ['value' => 0],
                'recipes' => $recipes,
            ];
        } catch (\Exception $e) {
            Log::error('Fehler beim Abrufen von verfügbaren Rezepten', [
                'patient_id' => $patient->id,
                'filters' => $merged,
                'error' => $e->getMessage(),
            ]);
            return ['recipe_ids' => [], 'total' => ['value' => 0]];
        }
    }

    /**
     * Hole Rezeptdetails für ein einzelnes Rezept über das dedizierte Endpoint.
     * Gibt alle verfügbaren Felder wie beim Batch-Call zurück.
     *
     * @param string|int $recipeId
     * @return array|null
     */
    public function fetchRecipeDetails($recipeId): ?array
    {
        $body = [
            'id' => $recipeId,
            'language' => 'de-de',
            'fields' => ['ingredients','steps','time','category','substances','media','images','difficulty','serving','diets','allergens','country','price','yield_quantity_1','yield_quantity_2','yield_info','yield_info_short','subtitle','alttitle','create','last_update','description'],
        ];
        $data = $this->makeApiRequest('POST', 'https://api.cookbutler.com/v1/recipes/recipe', $body);
        if (empty($data)) {
            Log::info('Keine Einzelrezeptdetails von der API zurückgegeben', ['rezept_id' => $recipeId]);
            return null;
        }
        $recipeData = $data['recipe'] ?? [];
        // Merge all top-level fields and 'recipe' fields, like in batch
        $merged = array_merge(
            ['id' => $recipeId, 'title' => $recipeData['title'] ?? "Rezept $recipeId"],
            $recipeData,
            $data // this will include media, images, allergens, etc.
        );
        return $merged;
    }

    /**
     * Get the user's filter preferences from the settings JSON.
     *
     * @param User $user
     * @return array
     */
    public function getUserFilterPreferences(User $user): array
    {
        $settings = $user->settings;
        if (is_string($settings)) {
            $settings = json_decode($settings, true);
        }
        if (!is_array($settings)) {
            return [];
        }
        $prefs = $settings['recipe_filter_set'] ?? [];
        // Map UI keys to API keys
        $map = [
            'filterTitle' => 'title',
            'filterIngredients' => 'ingredients',
            'filterAllergen' => 'allergen',
            'filterCategory' => 'category',
            'filterCountry' => 'country',
            'filterCourse' => 'courses',
            'filterDiets' => 'diets',
            'filterDifficulty' => 'difficulty',
            'filterMaxTime' => 'max_time',
        ];
        $result = [];
        foreach ($map as $from => $to) {
            if (isset($prefs[$from]) && $prefs[$from] !== '' && $prefs[$from] !== [] && $prefs[$from] !== null) {
                $result[$to] = $prefs[$from];
            }
        }
        return $result;
    }

    /**
     * Merge user filter preferences with provided filters. Provided filters take precedence.
     *
     * @param User $user
     * @param array $filters
     * @return array
     */
    public function mergeUserPreferencesWithFilters(User $user, array $filters = []): array
    {
        $prefs = $this->getUserFilterPreferences($user);
        // Provided filters take precedence
        return array_merge($prefs, $filters);
    }
}
