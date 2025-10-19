<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;
use Exception;
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
        \Illuminate\Support\Facades\Log::info('After adding recipe(s), current recipes', [
            'book_id' => $this->id,
            'recipe_count' => count($updatedRecipes),
            'recipe_ids' => $updatedRecipes,
        ]);
    }

    public function removeRecipe(int $recipeId): void
    {
        // Simple detach - no heavy operations
        $this->recipes()->detach($recipeId);

        // Optional: Clean up orphaned recipes (can be done in background job)
        // For now, skip the heavy user settings check to improve performance
        $recipe = \App\Models\Recipe::find($recipeId);
        if ($recipe && $recipe->books()->count() === 0) {
            // Only check if recipe is in any book, skip favorites check for performance
            $recipe->delete();
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

    /**
     * Check if a recipe can be added to the book without exceeding course limits
     */
    public function canAddRecipe($recipeId): array
    {
        $recipe = \App\Models\Recipe::find($recipeId);
        if (!$recipe) {
            return [
                'can_add' => false,
                'message' => __('Rezept nicht gefunden'),
                'course' => null,
                'current_count' => 0,
                'limit' => 0
            ];
        }

        // Get the recipe's course
        $categories = [];
        if (is_string($recipe->category ?? null)) {
            $categories = json_decode($recipe->category, true) ?: [];
        } elseif (is_array($recipe->category ?? null)) {
            $categories = $recipe->category;
        }
        $primaryCategory = \App\Filament\Resources\BookResource::getPrimaryCategory($categories);
        $course = \App\Filament\Resources\BookResource::mapCategoryToCourse($primaryCategory);

        // Get recipe limits
        $recipeLimits = $this->getRecipesPerCourse();

        // Count current recipes in this course
        $currentCount = $this->recipes()
            ->where('course', $course)
            ->count();

        $limit = $recipeLimits[$course] ?? PHP_INT_MAX;
        $canAdd = $currentCount < $limit;

        return [
            'can_add' => $canAdd,
            'message' => $canAdd ? null : __('Maximale Rezepteanzahl für :course erreicht! Aktuell: :current von :limit', [
                'course' => $course,
                'current' => $currentCount,
                'limit' => $limit
            ]),
            'course' => $course,
            'current_count' => $currentCount,
            'limit' => $limit
        ];
    }

    /**
     * Add a recipe to the book with limit checking and notification
     */
    public function addRecipeWithLimitCheck($recipeId): bool
    {
        $limitCheck = $this->canAddRecipe($recipeId);

        if (!$limitCheck['can_add']) {
            \Filament\Notifications\Notification::make()
                ->title(__('Rezeptlimit erreicht'))
                ->body($limitCheck['message'])
                ->warning()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('upgrade')
                        ->label(__('Konto upgraden'))
                        ->url('#')
                        ->color('success')
                        // ->visible(false) // Uncomment to hide upgrade button for now
                ])
                ->send();
            return false;
        }

        // Add the recipe
        $this->addRecipe($recipeId);
        return true;
    }

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class, 'analysis_id');
    }
}
