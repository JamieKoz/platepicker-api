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
}
