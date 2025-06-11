<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class UserMeal extends Model
{
    use HasFactory;

    protected $table = 'user_meals';

    protected $fillable = [
        'user_id',
        'recipe_id',
        'active',
        'title',
        'instructions',
        'image_name',
        'serves',
        'cooking_time',
    ];

    protected $appends = ['image_url'];

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

    public function userMealGroups(): HasMany
    {
        return $this->hasMany(UserMealGroup::class)->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        if ($this->image_name) {
            $extensions = ['jpg', 'jpeg', 'png', 'gif'];
            foreach ($extensions as $ext) {
                $path = "/user-meal-images/{$this->image_name}.{$ext}";
                if (Storage::disk('s3')->exists($path)) {
                return getenv('CLOUDFRONT_URL'). $path;
                }
            }
        }

        if ($this->recipe_id && $this->recipe && $this->recipe->image_name) {
            $recipePath = "/food-images/{$this->recipe->image_name}.jpg";
            if (Storage::disk('s3')->exists($recipePath)) {
                return getenv('CLOUDFRONT_URL'). $recipePath;
            }
        }

        return null;
    }
}
