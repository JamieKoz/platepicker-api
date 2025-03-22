<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measurement extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'abbreviation',
    ];

    /**
     * Get the recipe lines that use this measurement.
     */
    public function recipeLines(): HasMany
    {
        return $this->hasMany(RecipeLine::class);
    }
}
