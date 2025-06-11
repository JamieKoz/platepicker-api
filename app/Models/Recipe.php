<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'recipes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'instructions',
        'image_name',
        'serves',
        'cooking_time',
        'active',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_meals')
            ->withPivot('active')
            ->withTimestamps();
    }

   /**
     * Get the categories for this recipe.
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'recipe_categories');
    }

    /**
     * Get the cuisines for this recipe.
     */
    public function cuisines()
    {
        return $this->belongsToMany(Cuisine::class, 'recipes_cuisine');
    }

    /**
     * Get the dietary requirements for this recipe.
     */
    public function dietary()
    {
        return $this->belongsToMany(Dietary::class, 'recipes_dietary', 'recipe_id', 'dietary_id');
    }

    /**
     * Get the recipe lines for the recipe.
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)->orderBy('sort_order');
    }

    public function recipeGroups(): HasMany
    {
        return $this->hasMany(RecipeGroup::class)->orderBy('sort_order');
    }

    /**
     * Get recipe lines that don't belong to any group (ungrouped ingredients)
     */
    public function ungroupedRecipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)
            ->whereNull('recipe_group_id')
            ->orderBy('sort_order');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image_name) return null;

        return getenv('CLOUDFRONT_URL') . "/food-images/{$this->image_name}.jpg";
    }
}
