<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Services\CookButlerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RecipeViewController extends Controller
{
    protected $cookButlerService;

    public function __construct(CookButlerService $cookButlerService)
    {
        $this->cookButlerService = $cookButlerService;
    }

    public function show($id)
    {
        // First, try to find the recipe in the database by id_recipe or id_external
        $recipe = Recipe::where('id_recipe', $id)
            ->orWhere('id_external', $id)
            ->first();

        if ($recipe) {
            // Recipe exists in DB, use it
            // Return just the recipe content for modal display
            return view('filament.resources.recipe-resource.view-recipe-content', ['recipe' => $recipe]);
        }

        // Recipe not in DB, fetch from CookButler API
        try {
            Log::info('Fetching recipe from API', ['id' => $id]);
            
            $apiRecipe = $this->cookButlerService->getRecipeById($id);
            
            if (!$apiRecipe) {
                abort(404, 'Rezept nicht gefunden');
            }

            // Convert API response to array format for the view
            $recipeData = [
                'id' => $apiRecipe['id'] ?? $id,
                'id_recipe' => $apiRecipe['id_recipe'] ?? $apiRecipe['id'] ?? $id,
                'id_external' => $apiRecipe['id'] ?? $id,
                'title' => $apiRecipe['title'] ?? 'Unbekannt',
                'description' => $apiRecipe['description'] ?? '',
                'ingredients' => $apiRecipe['ingredients'] ?? [],
                'steps' => $apiRecipe['steps'] ?? [],
                'media' => $apiRecipe['media'] ?? [],
                'category' => $apiRecipe['category'] ?? [],
                'allergens' => $apiRecipe['allergens'] ?? [],
                'diets' => $apiRecipe['diets'] ?? [],
                'course' => $apiRecipe['course'] ?? '',
                'time' => $apiRecipe['time'] ?? [],
                'substances' => $apiRecipe['substances'] ?? [],
                'yield_quantity_1' => $apiRecipe['yield_quantity_1'] ?? $apiRecipe['serving'] ?? null,
                'yield_quantity_2' => $apiRecipe['yield_quantity_2'] ?? $apiRecipe['serving'] ?? null,
                'yield_info' => $apiRecipe['yield_info'] ?? 'Portionen',
                'country' => $apiRecipe['country'] ?? null,
                'difficulty' => $apiRecipe['difficulty'] ?? null,
                'images' => $apiRecipe['images'] ?? [],
            ];

            // Return just the recipe content for modal display
            return view('filament.resources.recipe-resource.view-recipe-content', ['recipe' => $recipeData]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch recipe from API', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            abort(404, 'Rezept konnte nicht geladen werden');
        }
    }
}
