<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the recipes that belong to this category.
     */
    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipe_categories');
    }

    public function userMeals()
    {
        return $this->belongsToMany(UserMeal::class, 'user_meals_categories');
    }
}
