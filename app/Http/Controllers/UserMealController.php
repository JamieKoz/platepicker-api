<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\UserMealService;
use App\Services\TallyService;
use App\Services\UserService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserMealController extends Controller
{
    protected $userMealService;
    protected $userService;
    protected $tallyService;
    protected $apiKey;
    protected $clerkUrl;

    public function __construct(UserMealService $userMealService, UserService $userService, TallyService $tallyService)
    {
        $this->userMealService = $userMealService;
        $this->userService = $userService;
        $this->tallyService = $tallyService;

        $this->apiKey = env('CLERK_API_KEY');
        $this->clerkUrl = env('CLERK_API_URL', 'https://api.clerk.com/v1');
    }

    public function validateUserExistsWithClerk($userId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->get($this->clerkUrl . '/users/' . $userId . '/organization_memberships');

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['data'])) {
                return true;
            }
        }

        return false;
    }

    public function getRecipe(Request $request)
    {
        $userId = $request->header('X-User-ID');

        // Extract filter parameters
        $categoryFilter = $request->query('categories');
        $cuisineFilter = $request->query('cuisines');
        $dietaryFilter = $request->query('dietary');

        if (!empty($userId)) {
            if (!$this->validateUserExistsWithClerk($userId)) {
                return response()->json(['error' => 'Unauthorized.'], 500);
            }

            // Pass filter parameters to service method
            $recipes = $this->userMealService->getRandomRecipesActive(
                27,
                $userId,
                $categoryFilter,
                $cuisineFilter,
                $dietaryFilter
            );

            return response()->json($recipes, 200);
        }

        // For unauthorized users, also support filtering
        $recipes = $this->userMealService->getRandomRecipesUnauthorized(
            27,
            $categoryFilter,
            $cuisineFilter,
            $dietaryFilter
        );

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

        if(!$this->validateUserExistsWithClerk($userId)){
            return response()->json(['error' => 'Unauthorized.'], 500);
        }

        $searchTerm = $request->query('q');
        $activeDirection = $request->query('active_direction', 'desc');
        $titleDirection = $request->query('title_direction', 'asc');
        $recipes = $this->userMealService->search($searchTerm, $userId, $activeDirection, $titleDirection);
        return response()->json($recipes);
    }

    public function getList(Request $request): JsonResponse
    {
        try {
            $userId = $request->header('X-User-ID');
            if (!$userId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            if(!$this->validateUserExistsWithClerk($userId)){
                return response()->json(['error' => 'Unauthorized.'], 500);
            }

            $activeDirection = $request->query('active_direction', 'desc');
            $titleDirection = $request->query('title_direction', 'asc');
            $list = $this->userMealService->getRecipeList($userId, $activeDirection, $titleDirection);
            return response()->json($list);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' =>$e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $userId = $request->header('X-User-ID');

            if (!$userId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            if(!$this->validateUserExistsWithClerk($userId)){
                return response()->json(['error' => 'Unauthorized.'], 500);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'ingredients' => 'string',
                'instructions' => 'string',
                'cooking_time' => 'nullable|string',
                'serves' => 'nullable|string',
                'categories' => 'nullable|array',
                'categories.*' => 'exists:categories,id',
                'cuisines' => 'nullable|array',
                'cuisines.*' => 'exists:cuisines,id',
                'dietary' => 'nullable|array',
                'dietary.*' => 'exists:dietary,id',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);

            $recipe = $this->userMealService->createRecipe($validated, $userId);
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
                'cooking_time' => 'nullable|string',
                'serves' => 'nullable|string',
                'categories' => 'nullable|array',
                'categories.*' => 'exists:categories,id',
                'cuisines' => 'nullable|array',
                'cuisines.*' => 'exists:cuisines,id',
                'dietary' => 'nullable|array',
                'dietary.*' => 'exists:dietary,id',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);

            $recipe = $this->userMealService->updateRecipe($id, $validated, $authId);
            return response()->json($recipe);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
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
            $this->userMealService->toggleStatus($authId, $id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to toggle status'], 500);
        }
    }

    public function addFromRecipe(Request $request, $id): JsonResponse
    {
        try {
            $authId = $request->header('X-User-ID');
            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $userMeal = $this->userMealService->addFromRecipe($authId, $id);
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

            $this->userMealService->deleteMeal($authId, $id);
            return response()->json(['message' => 'Meal deleted successfully']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to delete meal'], 500);
        }
    }
}
