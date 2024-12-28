<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\RecipeService;

class RecipeController extends Controller
{
    public function getRecipe()
    {
        $recipeService = new RecipeService();
        $recipes = $recipeService->getRandomRecipesInactive(27);

        return response()->json($recipes, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }

    public function getList()
    {
        $recipeService = new RecipeService();
        $list = $recipeService->getRecipeList();
        return response()->json($list, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }

    public function toggleStatus($mealId){
        $recipeService = new RecipeService();
        $recipe = $recipeService->toggleStatus($mealId);
        return response()->json($recipe, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }

    public function search(Request $request){
        $searchTerm = $request->query('q');
        $recipeService = new RecipeService();
        $recipes = $recipeService->search($searchTerm);

        return response()->json($recipes, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }
}
