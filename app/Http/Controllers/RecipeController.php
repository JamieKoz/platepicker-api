<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\RecipeService;

class RecipeController extends Controller
{
  protected $recipeService;

    public function __construct(RecipeService $recipeService)
    {
        $this->recipeService = $recipeService;
    }

    public function getRecipe()
    {
        $recipes = $this->recipeService->getRandomRecipesActive(27);

        return response()->json($recipes, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }

    public function getList(): JsonResponse
    {
        try {
            $list = $this->recipeService->getRecipeList();
            return response()->json($list);
        } catch (\Exception $e) {
            /* \Log::error('Recipe list error: ' . $e->getMessage()); */
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }

    public function toggleStatus($mealId){
        $recipe = $this->recipeService->toggleStatus($mealId);
        return response()->json($recipe, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, GET, OPTIONS, PUT, DELETE',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }

    public function search(Request $request)
    {
        $searchTerm = $request->query('q');

        $recipes = $this->recipeService->search($searchTerm);

        return response()->json($recipes);
    }
}
