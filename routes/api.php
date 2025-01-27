<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;

use App\Http\Controllers\RecipeController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/recipe', [RecipeController::class, 'getRecipe']);
Route::get('/user-meals/list', [RecipeController::class, 'getList']);
Route::get('/user-meals/search', [RecipeController::class, 'search']);
Route::post('/user-meals', [RecipeController::class, 'store']);
Route::post('/user-meals/{id}', [RecipeController::class, 'update']);
Route::post('/user-meals/{id}/toggle-status', [RecipeController::class, 'toggleStatus']);

Route::get('/recipes', [RecipeController::class, 'getRecipes']);
Route::post('/user-meals/add-from-recipe/{id}', [RecipeController::class, 'addFromRecipe']);
Route::delete('/user-meals/{id}', [RecipeController::class, 'destroy']);
Route::post('/user-meals/{id}/increment-tally', [RecipeController::class, 'incrementTally']);
