<?php

use App\Http\Controllers\BaseRecipeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TallyController;
use App\Http\Controllers\UserMealController;
use App\Http\Controllers\RestaurantController;
use App\Http\Controllers\CuisineController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DietaryController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\IngredientController;
use App\Http\Controllers\MeasurementController;
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

Route::get('/recipes', [BaseRecipeController::class, 'getRecipes']);
Route::delete('/recipes/{id}', [BaseRecipeController::class, 'destroy']);
Route::post('/recipes/{id}/toggle-status', [BaseRecipeController::class, 'toggleStatus']);
Route::get('/recipes/list', [BaseRecipeController::class, 'getList']);
Route::get('/recipes/search', [BaseRecipeController::class, 'search']);
Route::post('/recipes', [BaseRecipeController::class, 'store']);
Route::post('/recipes/{id}', [BaseRecipeController::class, 'update']);
Route::get('/recipes/group-values', [BaseRecipeController::class, 'getGroupValues']);
Route::post('/users/assign-initial-recipes', [BaseRecipeController::class, 'assignInitialMealsToUser']);

// Category routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::post('/categories', [CategoryController::class, 'store']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);
Route::put('/categories/{id}', [CategoryController::class, 'update']);
Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

// Cuisine routes
Route::get('/cuisines', [CuisineController::class, 'index']);
Route::post('/cuisines', [CuisineController::class, 'store']);
Route::get('/cuisines/{id}', [CuisineController::class, 'show']);
Route::put('/cuisines/{id}', [CuisineController::class, 'update']);
Route::delete('/cuisines/{id}', [CuisineController::class, 'destroy']);

// Dietary routes
Route::get('/dietary', [DietaryController::class, 'index']);
Route::post('/dietary', [DietaryController::class, 'store']);
Route::get('/dietary/{id}', [DietaryController::class, 'show']);
Route::put('/dietary/{id}', [DietaryController::class, 'update']);
Route::delete('/dietary/{id}', [DietaryController::class, 'destroy']);

// Measurement routes
Route::get('/measurements', [MeasurementController::class, 'index']);
Route::post('/measurements', [MeasurementController::class, 'store']);
Route::get('/measurements/{id}', [MeasurementController::class, 'show']);
Route::put('/measurements/{id}', [MeasurementController::class, 'update']);
Route::delete('/measurements/{id}', [MeasurementController::class, 'destroy']);

// Ingredient routes
Route::get('/ingredients', [IngredientController::class, 'index']);
Route::get('/ingredients/search', [IngredientController::class, 'search']);
Route::post('/ingredients', [IngredientController::class, 'store']);
Route::get('/ingredients/{id}', [IngredientController::class, 'show']);
Route::put('/ingredients/{id}', [IngredientController::class, 'update']);
Route::delete('/ingredients/{id}', [IngredientController::class, 'destroy']);

// Feedback routes
Route::post('/feedback', [FeedbackController::class, 'submit']);
Route::get('/feedback', [FeedbackController::class, 'index']);
Route::get('/feedback/{id}', [FeedbackController::class, 'show']);
Route::put('/feedback/{id}', [FeedbackController::class, 'update']);

// routes/api.php
Route::post('/users/register-clerk-user', [UserController::class, 'store']);
