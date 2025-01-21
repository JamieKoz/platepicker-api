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

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'ingredients' => 'string',
                'instructions' => 'string',
                'image' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);

            $recipe = $this->recipeService->createRecipe($validated);
            return response()->json($recipe, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create recipe'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);

            $recipe = $this->recipeService->updateRecipe($id, $validated);
            return response()->json($recipe);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update recipe'], 500);
        }
    }
}
