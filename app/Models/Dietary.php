<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dietary extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'dietary';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * Get the recipes that have this dietary requirement.
     */
    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'recipes_dietary', 'dietary_id', 'recipe_id');
    }

    /**
     * Get the recipes that have this dietary requirement.
     */
    public function userMeals()
    {
        return $this->belongsToMany(UserMeal::class, 'user_meals_dietary', 'dietary_id', 'user_meals_id');
    }
}

