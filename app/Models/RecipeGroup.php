<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecipeGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'name',
        'description',
        'sort_order',
    ];

    /**
     * Get the recipe that owns this group.
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Get the recipe lines for this group.
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class)->orderBy('sort_order');
    }

    /**
     * Scope to order groups by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
