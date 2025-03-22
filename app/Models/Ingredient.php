<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Get the recipe lines that use this ingredient.
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }
}
