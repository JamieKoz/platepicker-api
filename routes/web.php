<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MealController;
use App\Http\Controllers\RecipeController;
use Fruitcake\Cors\HandleCors;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // http::get('https://www.themealdb.com/api/json/v1/1/random.php');
    return view('welcome');
});

Route::get('/csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
});
// Route::get('/meal', [MealController::class, 'showMeal'])->name('meal');
// Route::post('/choose-meal', [MealController::class, 'chooseMeal'])->name('choose-meal');

/* Route::get('/recipe', [RecipeController::class, 'getRecipe'])->name(''); */
/* Route::get('/list', [RecipeController::class, 'getList'])->name(''); */
/* Route::post('/meal/{mealId}/toggle-status', [RecipeController::class, 'toggleStatus']); */
/* Route::get('/search', [RecipeController::class, 'search']); */
