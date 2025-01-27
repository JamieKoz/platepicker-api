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
        'recipe_id',
        'tally',
        'last_selected_at'
    ];

    protected $casts = [
        'last_selected_at' => 'datetime',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function incrementSelection()
    {
        $this->tally++;
        $this->last_selected_at = now();
        $this->save();
    }
}
