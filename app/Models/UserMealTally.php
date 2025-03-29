<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMealTally extends Model
{
    use HasFactory;

    protected $table = 'user_meal_tally';

    protected $fillable = [
        'user_id',
        'user_meal_id',
        'recipe_id',
        'tally',
        'last_selected_at'
    ];

    protected $casts = [
        'last_selected_at' => 'datetime',
    ];

    public function userMeal()
    {
        return $this->belongsTo(UserMeal::class, 'user_meal_id');
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }

    public function incrementSelection()
    {
        $this->tally++;
        $this->last_selected_at = now();
        $this->save();
    }
}
