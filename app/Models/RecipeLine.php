<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'user_meal_id',
        'ingredient_id',
        'quantity',
        'measurement_id',
        'notes',
        'recipe_group_id',
        'user_meal_group_id',
        'sort_order',
    ];

    /**
     * Get the recipe that owns the recipe line.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the ingredient associated with the recipe line.
     */
    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    /**
     * Get the measurement associated with the recipe line.
     */
    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    public function userMeal(): BelongsTo
    {
        return $this->belongsTo(UserMeal::class);
    }

    public function recipeGroup(): BelongsTo
    {
        return $this->belongsTo(RecipeGroup::class);
    }

    public function userMealGroup(): BelongsTo
    {
        return $this->belongsTo(UserMealGroup::class);
    }

    /**
     * Scope to get recipe lines grouped by recipe group (for recipes)
     */
    public function scopeGroupedByRecipeGroup($query)
    {
        return $query->with(['recipeGroup', 'ingredient', 'measurement'])
            ->orderBy('recipe_group_id')
            ->orderBy('sort_order');
    }

    /**
     * Scope to get recipe lines grouped by user meal group (for user meals)
     */
    public function scopeGroupedByUserMealGroup($query)
    {
        return $query->with(['userMealGroup', 'ingredient', 'measurement'])
            ->orderBy('user_meal_group_id')
            ->orderBy('sort_order');
    }

    /**
     * General scope for grouped recipe lines that works for both contexts
     */
    public function scopeGrouped($query, $type = 'recipe')
    {
        if ($type === 'userMeal') {
            return $this->scopeGroupedByUserMealGroup($query);
        }

        return $this->scopeGroupedByRecipeGroup($query);
    }

    /**
     * Scope to get ungrouped recipe lines for recipes
     */
    public function scopeUngroupedRecipe($query)
    {
        return $query->whereNull('recipe_group_id')
            ->with(['ingredient', 'measurement'])
            ->orderBy('sort_order');
    }

    /**
     * Scope to get ungrouped recipe lines for user meals
     */
    public function scopeUngroupedUserMeal($query)
    {
        return $query->whereNull('user_meal_group_id')
            ->with(['ingredient', 'measurement'])
            ->orderBy('sort_order');
    }

    /**
     * Get the group name for this recipe line (works for both contexts)
     */
    public function getGroupNameAttribute(): ?string
    {
        if ($this->recipe_group_id && $this->recipeGroup) {
            return $this->recipeGroup->name;
        }

        if ($this->user_meal_group_id && $this->userMealGroup) {
            return $this->userMealGroup->name;
        }

        return null;
    }

    /**
     * Get the group ID for this recipe line (works for both contexts)
     */
    public function getGroupIdAttribute(): ?int
    {
        return $this->recipe_group_id ?? $this->user_meal_group_id;
    }
}
