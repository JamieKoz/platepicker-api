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

    public function getList(Request $request): JsonResponse
    {
        try {
            $activeDirection = $request->query('active_direction', 'desc');
            $titleDirection = $request->query('title_direction', 'asc');
            $list = $this->recipeService->getRecipeList($activeDirection, $titleDirection);
            return response()->json($list);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }

    public function search(Request $request)
    {
        $searchTerm = $request->query('q');
        $activeDirection = $request->query('active_direction', 'desc');
        $titleDirection = $request->query('title_direction', 'asc');
        $recipes = $this->recipeService->search($searchTerm, $activeDirection, $titleDirection);
        return response()->json($recipes);
    }
}
