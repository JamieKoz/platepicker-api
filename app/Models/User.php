<?php

namespace App\Models;

use App\Services\UserMealService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'auth_id',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function meals()
    {
        return $this->belongsToMany(Recipe::class, 'user_meals')
            ->withPivot('active')
            ->withTimestamps();
    }

    protected static function booted() {
        static::created(function ($user) {
        $recipeService = app(RecipeService::class);
            $recipeService->assignInitialMealsToUser($user);
        });
    }
}
