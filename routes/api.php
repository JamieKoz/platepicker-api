<?php

use App\Http\Controllers\BaseRecipeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TallyController;
use App\Http\Controllers\UserMealController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\UserController;
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

Route::get('/recipe', [UserMealController::class, 'getRecipe']);
Route::get('/user-meals/list', [UserMealController::class, 'getList']);
Route::get('/user-meals/search', [UserMealController::class, 'search']);
Route::post('/user-meals', [UserMealController::class, 'store']);
Route::post('/user-meals/{id}', [UserMealController::class, 'update']);
Route::post('/user-meals/{id}/toggle-status', [UserMealController::class, 'toggleStatus']);

Route::post('/user-meals/add-from-recipe/{id}', [UserMealController::class, 'addFromRecipe']);
Route::delete('/user-meals/{id}', [UserMealController::class, 'destroy']);
Route::post('/user-meals/{id}/increment-tally', [TallyController::class, 'incrementTally']);

Route::get('/user-meals/favourites', [TallyController::class, 'getFavourites']);
Route::get('/user-meals/top-meals', [TallyController::class, 'getTopMeals']);

Route::get('/restaurants/nearby', [RestaurantController::class, 'getNearbyRestaurants']);
Route::get('/restaurants/address-suggestions', [RestaurantController::class, 'getAddressSuggestions']);
Route::get('/restaurants/reverse-geocode', [RestaurantController::class, 'reverseGeocode']);
Route::get('/restaurants/photos/{placeId}', [RestaurantController::class, 'getRestaurantPhotos']);
Route::get('/restaurants/photo-proxy', [RestaurantController::class, 'getPhotoProxy']);

Route::get('/users/list', [UserController::class, 'getUsers']);
Route::get('/users/search', [UserController::class, 'searchUsers']);
Route::put('/users/{id}', [UserController::class, 'updateUser']);

Route::get('/recipes', [BaseRecipeController::class, 'getRecipes']);
Route::delete('/recipes/{id}', [BaseRecipeController::class, 'destroy']);
Route::post('/recipes/{id}/toggle-status', [BaseRecipeController::class, 'toggleStatus']);
Route::get('/recipes/list', [BaseRecipeController::class, 'getList']);
Route::get('/recipes/search', [BaseRecipeController::class, 'search']);
Route::post('/recipes', [BaseRecipeController::class, 'store']);
Route::post('/recipes/{id}', [BaseRecipeController::class, 'update']);
Route::post('/users/assign-initial-recipes', [BaseRecipeController::class, 'assignInitialMealsToUser']);
