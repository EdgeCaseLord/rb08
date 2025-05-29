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

    public function addRecipe(int $recipeId): void
    {
        $this->recipes()->syncWithoutDetaching([$recipeId]);
        $updatedRecipes = $this->recipes()->pluck('id_recipe')->toArray();
        Log::info('After adding recipe, current recipes', [
            'book_id' => $this->id,
            'recipe_count' => count($updatedRecipes),
            'recipe_ids' => $updatedRecipes,
        ]);
    }

    public function removeRecipe(int $recipeId): void
    {
        $this->recipes()->detach($recipeId);
        $updatedRecipes = $this->recipes()->pluck('id_recipe')->toArray();
        Log::info('After removing recipe, current recipes', [
            'book_id' => $this->id,
            'recipe_count' => count($updatedRecipes),
            'recipe_ids' => $updatedRecipes,
        ]);
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
