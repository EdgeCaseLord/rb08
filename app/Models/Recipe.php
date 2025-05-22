<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Recipe extends Model
{
    use HasFactory;

    protected $table = 'recipes';
    protected $primaryKey = 'id_recipe';

    protected $fillable = [
        'id_external',
        'title',
        'subtitle',
        'description',
        'category',
        'substances',
        'media',
        'images',
        'serving',
        'language',
        'difficulty',
        'time',
        'steps',
        'ingredients',
        'diets',
        'allergens',
        'course',
        'yield_quantity_1',
        'yield_quantity_2',
        'yield_info',
        'yield_info_short',
        'price',
        'suitable_for_pregnancy',
        'alttitle',
        'create',
        'last_update',
    ];

    protected $casts = [
        'category' => 'array',
        'substances' => 'array',
        'media' => 'array',
        'images' => 'array',
        'time' => 'array',
        'steps' => 'array',
        'ingredients' => 'array',
        'diets' => 'array',
        'allergens' => 'array',
        'create' => 'datetime',
        'last_update' => 'datetime',
    ];

    // public function ingredients(): BelongsToMany
    // {
    //     return $this->belongsToMany(Ingredient::class, 'ingredient_recipe', 'recipe_id', 'ingredient_id');
    // }

    // public function allergens(): BelongsToMany
    // {
    //     return $this->belongsToMany(Allergen::class, 'allergen_recipe', 'recipe_id', 'allergen_id');
    // }

    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class, 'book_recipe', 'recipe_id', 'book_id')->withTimestamps();
    }

    public function countries(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Country::class, 'country_recipe', 'recipe_id', 'country_id');
    }
}
