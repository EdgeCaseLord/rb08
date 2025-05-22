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

    public function __construct()
    {
        $this->apiKey = config('cookbutler.api_key', '');
        $this->apiSecret = config('cookbutler.api_secret', '');
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            Log::error('CookButler API-Zugangsdaten nicht konfiguriert');
        }
    }

    protected function generateJwt(): string
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
    public function buildSearchQuery(User $patient): string
    {
        // Only use allergens directly linked to this user via allergen_user
        $allergens = $patient->allergens()->wherePivot('user_id', $patient->id)->get();
        $codes = $allergens->map(function ($allergen) {
            $name = strtolower($allergen->name);
            $name = str_replace([' ', ',', '-', '(', ')', '*', "'", "."], ['_', '', '', '', '', '', '', ''], $name);
            return "-- allergen_pro_{$name}";
        })->unique()->toArray();
        return empty($codes) ? '' : implode(' ', $codes);
    }

    protected function makeApiRequest(string $method, string $url, array $data = []): ?array
    {
        $jwt = $this->generateJwt();
        $headers = [
            'Authorization' => "Bearer $jwt",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

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
            $query = $this->buildSearchQuery($patient);
            $searchData = [
                'language' => 'de-de',
                'searchtype' => 'extended',
                'add_info' => ['title', 'difficulty', 'time_total', 'category', 'substances', 'country', 'serving', 'diets'],
                'filters' => ['course' => [$course]],
                'q' => $query,
                'limit' => min($limit, 100),
                'offset' => $offset,
            ];

            Log::debug('CookButler search request', [
                'patient_id' => $patient->id,
                'course' => $course,
                'query' => $query
            ]);

            $data = $this->makeApiRequest('POST', $this->searchEndpoint, $searchData);
            $recipes = $data['recipes'] ?? [];

            if (empty($recipes)) {
                Log::info('Keine Rezepte von der Such-API zurückgegeben', [
                    'patient_id' => $patient->id,
                    'gang' => $course,
                    'query' => $query,
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
     * @param User|null $patient Optional: Patient for allergen exclusion
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
            if ($patient) {
                $query = $this->buildSearchQuery($patient);
                if (!empty($query)) {
                    $batchData['q'] = $query;
                }
            }
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
        Log::info('fetchAvailableRecipesForPatient called', [
            'patient_id' => $patient->id,
            'filters' => $filters,
            'limit' => $limit,
            'offset' => $offset,
        ]);
        try {
            $query = $this->buildSearchQuery($patient);
            $searchData = [
                'language' => 'de-de',
                'searchtype' => 'extended',
                'add_info' => ['title', 'difficulty', 'time_total', 'category', 'substances', 'country', 'serving', 'diets', 'media', 'images', 'allergens', 'description'],
                'limit' => min($limit, 100),
                'offset' => $offset,
            ];
            $apiFilters = [];
            if (!empty($filters['courses'])) {
                $apiFilters['course'] = (array)$filters['courses'];
            }
            if (!empty($filters['difficulty'])) {
                $apiFilters['difficulty'] = (array)$filters['difficulty'];
            }
            if (!empty($filters['ingredients'])) {
                $apiFilters['ingredients'] = (array)$filters['ingredients'];
            }
            if (!empty($filters['diets'])) {
                $apiFilters['diet'] = (array)$filters['diets'];
            }
            if (!empty($filters['allergen'])) {
                $apiFilters['allergen'] = (array)$filters['allergen'];
            }
            if (!empty($filters['category'])) {
                $apiFilters['category'] = (array)$filters['category'];
            }
            if (!empty($filters['country'])) {
                $apiFilters['country'] = (array)$filters['country'];
            }
            if (!empty($filters['max_time'])) {
                $apiFilters['max_time'] = (array)$filters['max_time'];
            }
            // Always append allergen exclusion query to the search string
            $searchString = '';
            if (!empty($filters['title'])) {
                $searchString = $filters['title'] . ' ' . $query;
            } else {
                $searchString = $query;
            }
            $searchData['q'] = trim($searchString);
            if (!empty($apiFilters)) {
                $searchData['filters'] = $apiFilters;
            }

            Log::info('CookButler available recipes search - about to make API request', [
                'patient_id' => $patient->id,
                'filters' => $filters,
                'apiFilters' => $apiFilters,
                'searchData' => $searchData,
            ]);

            $data = $this->makeApiRequest('POST', $this->searchEndpoint, $searchData);
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
                \Illuminate\Support\Facades\Log::debug('Recipe category values', ['catVals' => $catVals, 'recipe' => $recipe]);
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
                    'filters' => $filters,
                    'query' => $searchData['q'],
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
                'filters' => $filters,
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
     * @param User|null $patient Optional: Patient for allergen exclusion
     * @return array|null
     */
    public function fetchRecipeDetails($recipeId, User $patient = null): ?array
    {
        $jwt = $this->generateJwt();
        $headers = [
            'Authorization' => "Bearer $jwt",
            'Accept' => 'application/json',
        ];
        $url = 'https://api.cookbutler.com/v1/recipes/recipe';
        $query = [
            'id' => $recipeId,
            'language' => 'de-de',
            'fields' => ['ingredients','steps','time','category','substances','media','images','difficulty','serving','diets','allergens','country','price','yield_quantity_1','yield_quantity_2','yield_info','yield_info_short','subtitle','alttitle','create','last_update','description'],
        ];
        if ($patient) {
            $allergenQ = $this->buildSearchQuery($patient);
            if (!empty($allergenQ)) {
                $query['q'] = $allergenQ;
            }
        }
        $response = \Illuminate\Support\Facades\Http::withHeaders($headers)->get($url, $query);
        if (!$response->successful()) {
            Log::error('Fehler beim Abrufen von Einzelrezeptdetails', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }
        $data = $response->json('data');
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
}
