<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cuisine extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'value',
    ];

    /**
     * Get the recipes that belong to this cuisine.
     */
    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipes_cuisine');
    }


    public function userMeals()
    {
        return $this->belongsToMany(UserMeal::class, 'user_meals_cuisine');
    }
}
