<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\RecipeService;
use App\Services\TallyService;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;

class RecipeController extends Controller
{
    protected $recipeService;
    protected $userService;
    protected $tallyService;

    public function __construct(RecipeService $recipeService, UserService $userService, TallyService $tallyService)
    {
        $this->recipeService = $recipeService;
        $this->userService = $userService;
        $this->tallyService = $tallyService;
    }

    public function getRecipe()
    {
        $recipes = $this->recipeService->getRandomRecipesActive(27);

        return response()->json($recipes, 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Origin, Content-Type, X-Auth-Token'
        ]);
    }

    public function search(Request $request)
    {
        $userId = $request->header('X-User-ID');
        if (!$userId) {
            return response()->json(['error' => 'User ID required'], 400);
        }
        $searchTerm = $request->query('q');
        $activeDirection = $request->query('active_direction', 'desc');
        $titleDirection = $request->query('title_direction', 'asc');
        $recipes = $this->recipeService->search($searchTerm, $userId, $activeDirection, $titleDirection);
        return response()->json($recipes);
    }

    public function getList(Request $request): JsonResponse
    {
        try {
            $userId = $request->header('X-User-ID');
            if (!$userId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $activeDirection = $request->query('active_direction', 'desc');
            $titleDirection = $request->query('title_direction', 'asc');
            $list = $this->recipeService->getRecipeList($userId, $activeDirection, $titleDirection);
            return response()->json($list);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $authId = $request->header('X-User-ID');

            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'ingredients' => 'string',
                'instructions' => 'string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);

            $recipe = $this->recipeService->createRecipe($validated, $authId);
            return response()->json($recipe, 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to create recipe'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        $authId = $request->header('X-User-ID');
        if (!$authId) {
            return response()->json(['error' => 'User ID required'], 400);
        }

        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'ingredients' => 'nullable|string',
                'instructions' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);
            $recipe = $this->recipeService->updateRecipe($id, $validated, $authId);
            return response()->json($recipe);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
            return response()->json(['error' => 'Failed to update recipe'], 500);
        }
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {

        $authId = $request->header('X-User-ID');
        if (!$authId) {
            return response()->json(['error' => 'User ID required'], 400);
        }
        try {
            $this->recipeService->toggleStatus($authId, $id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to toggle status'], 500);
        }
    }

    public function getRecipes(Request $request): JsonResponse
    {
        try {
            $searchTerm = $request->query('q');
            $titleDirection = $request->query('title_direction', 'asc');

            $recipes = $this->recipeService->getRecipes($searchTerm, $titleDirection);
            return response()->json($recipes);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }

    public function addFromRecipe(Request $request, $id): JsonResponse
    {
        try {
            $authId = $request->header('X-User-ID');
            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $userMeal = $this->recipeService->addFromRecipe($authId, $id);
            return response()->json($userMeal, 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to add recipe to meals'], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $authId = $request->header('X-User-ID');
            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $this->recipeService->deleteMeal($authId, $id);
            return response()->json(['message' => 'Meal deleted successfully']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to delete meal'], 500);
        }
    }

    public function incrementTally(Request $request, $id): JsonResponse
    {
        try {
            $authId = $request->header('X-User-ID');
            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $this->tallyService->incrementMealTally($authId, $id);
            return response()->json(['message' => 'Tally incremented successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' =>$e->getMessage()], 500);
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to increment tally'], 500);
        }
    }
}
