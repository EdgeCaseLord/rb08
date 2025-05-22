<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Allergen extends Model
{
    use HasFactory;

    protected $table = 'allergens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'name_latin',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
    ];

    // public function recipes()
    // {
    //     return $this->belongsToMany(Recipe::class, 'allergen_recipe', 'allergen_id', 'recipe_id');
    // }

    // public function ingredients()
    // {
    //     return $this->belongsToMany(Ingredient::class, 'allergen_ingredient', 'allergen_id', 'ingredient_id');
    // }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function analysisAllergens()
    {
        return $this->hasMany(AnalysisAllergen::class);
    }
}
