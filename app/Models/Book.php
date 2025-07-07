<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Book extends Model
{
    use HasFactory;

    protected $table = 'books';
    protected $fillable = [
        'title',
        'patient_id',
        'analysis_id',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'patient_id' => 'integer',
        'analysis_id' => 'integer',
        'status' => 'string',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id')->where('role', 'patient');
    }

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'book_recipe', 'book_id', 'recipe_id')->withTimestamps();
    }

    /**
     * Add one or more recipes to the book, ensuring they exist locally (batch fetch if needed).
     * @param int|array $recipeIds
     */
    public function addRecipe($recipeIds): void
    {
        $ids = is_array($recipeIds) ? $recipeIds : [$recipeIds];
        // Ensure all recipes exist locally (batch fetch if needed)
        $service = app(\App\Services\CookButlerService::class);
        $patient = $this->patient;
        $service->ensureRecipesExist($ids, $patient);
        // Map all input IDs (API or local) to local id_recipe
        $localIds = collect($ids)
            ->map(function($id) {
                // Try as local PK
                $recipe = \App\Models\Recipe::find($id);
                if ($recipe) return $recipe->id_recipe;
                // Try as API id_external
                $recipe = \App\Models\Recipe::where('id_external', $id)->first();
                return $recipe ? $recipe->id_recipe : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (!empty($localIds)) {
            $this->recipes()->syncWithoutDetaching($localIds);
        }
        $updatedRecipes = $this->recipes()->pluck('id_recipe')->toArray();
        \Log::info('After adding recipe(s), current recipes', [
            'book_id' => $this->id,
            'recipe_count' => count($updatedRecipes),
            'recipe_ids' => $updatedRecipes,
        ]);
    }

    public function removeRecipe(int $recipeId): void
    {
        $this->recipes()->detach($recipeId);
        $updatedRecipes = $this->recipes()->pluck('id_recipe')->toArray();
        \Log::info('After removing recipe, current recipes', [
            'book_id' => $this->id,
            'recipe_count' => count($updatedRecipes),
            'recipe_ids' => $updatedRecipes,
        ]);
        // Remove recipe if not referenced by any book or as a favourite
        $recipe = \App\Models\Recipe::find($recipeId);
        if ($recipe) {
            $bookCount = $recipe->books()->count();
            // Check for favourites in user settings
            $isFavourite = false;
            $users = \App\Models\User::whereNotNull('settings')->get();
            foreach ($users as $user) {
                $settings = is_string($user->settings) ? json_decode($user->settings, true) : $user->settings;
                if (isset($settings['favorites']) && is_array($settings['favorites']) && (in_array($recipe->id_external, $settings['favorites']) || in_array($recipe->id_recipe, $settings['favorites']))) {
                    $isFavourite = true;
                    break;
                }
            }
            if ($bookCount === 0 && !$isFavourite) {
                $recipe->delete();
                \Log::info('Recipe deleted as it is no longer referenced', [
                    'recipe_id' => $recipeId
                ]);
            }
        }
    }

    public function getRecipesPerCourse(): array
    {
        $defaultRecipesPerCourse = [
            'starter' => 5,
            'main_course' => 5,
            'dessert' => 5,
        ];

        $patientSettings = $this->patient ? ($this->patient->settings['recipes_per_course'] ?? []) : [];
        $labSettings = $this->patient && $this->patient->lab ? ($this->patient->lab->settings['recipes_per_course'] ?? []) : [];

        return [
            'starter' => $patientSettings['starter'] ?? $labSettings['starter'] ?? $defaultRecipesPerCourse['starter'],
            'main_course' => $patientSettings['main_course'] ?? $labSettings['main_course'] ?? $defaultRecipesPerCourse['main_course'],
            'dessert' => $patientSettings['dessert'] ?? $labSettings['dessert'] ?? $defaultRecipesPerCourse['dessert']
        ];
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id');
    }
}
