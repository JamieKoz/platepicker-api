<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserMeal extends Model
{
    use HasFactory;

    protected $table = 'user_meals';

    protected $fillable = [
        'user_id',
        'recipe_id',
        'active',
        'title',
        'ingredients',
        'instructions',
        'image_name',
        'cleaned_ingredients',
        'serves',
        'cooking_time',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user()
    {
        return null;
    }

    /**
     * Get the categories for this recipe.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'user_meals_categories');
    }

    /**
     * Get the cuisines for this recipe.
     */
    public function cuisines()
    {
        return $this->belongsToMany(Cuisine::class, 'user_meals_cuisine');
    }

    /**
     * Get the dietary requirements for this recipe.
     */
    public function dietary()
    {
        return $this->belongsToMany(Dietary::class, 'user_meals_dietary', 'user_meals_id', 'dietary_id');
    }


    /**
     * Get the recipe lines for the recipe.
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)->orderBy('sort_order');
    }
}
