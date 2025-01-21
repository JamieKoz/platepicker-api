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
Route::get('/list', [RecipeController::class, 'getList']);
Route::post('/meal/{mealId}/toggle-status', [RecipeController::class, 'toggleStatus']);
Route::get('/search', [RecipeController::class, 'search']);

Route::post('/meal', [RecipeController::class, 'store']);
Route::post('/meal/{id}', [RecipeController::class, 'update']);

Route::post('/register', RegisterController::class );
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');
