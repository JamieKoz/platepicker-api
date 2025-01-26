<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMeal extends Model
{
    use HasFactory;

    protected $table = 'user_meals';

    protected $fillable = [
        'user_id',
        'recipe_id',
        'active',
        'title',
        'ingredients',
        'instructions',
        'image_name',
        'cleaned_ingredients'
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
