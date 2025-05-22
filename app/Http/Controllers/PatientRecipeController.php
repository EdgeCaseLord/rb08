<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CookButlerService;
use Illuminate\Http\Request;

class PatientRecipeController extends Controller
{
    public function getRecipes(User $patient, CookButlerService $service)
    {
        $recipes = $service->getSafeRecipesForPatient($patient);

        if (empty($recipes)) {
            return response()->json(['message' => 'Keine sicheren Rezepte gefunden'], 404);
        }

        return response()->json([
            'patient' => $patient->name,
            'allergens' => $patient->allergens()->pluck('code')->toArray(),
            'recipes' => $recipes,
        ]);
    }
}
