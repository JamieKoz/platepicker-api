<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\UserService;
use App\Services\BaseRecipeService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseRecipeController extends Controller
{
    protected $userService;
    protected $baseRecipeService;

    protected $apiKey;
    protected $clerkUrl;


    public function __construct(UserService $userService, BaseRecipeService $baseRecipeService)
    {
        $this->userService = $userService;
        $this->baseRecipeService = $baseRecipeService;

        $this->apiKey = env('CLERK_API_KEY');
        $this->clerkUrl = env('CLERK_API_URL', 'https://api.clerk.com/v1');
    }

    public function assignInitialMealsToUser(Request $request)
    {
        $userId = null;
        $userData = json_decode($request->header('X-User-Data'), true);
        if ($userData && isset($userData['id'])) {
            $userId = $userData['id'];
        }
        $this->baseRecipeService->assignInitialMealsToUser($userId);
        return response()->json(['success' => 'Assigned Recipes to new user.'], 200);
    }

    public function validateIsAdminWithClerk($userId): bool
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->get($this->clerkUrl . '/users/' . $userId . '/organization_memberships');

        if ($response->successful()) {
            $data = $response->json();
            if (!empty($data['data']) && count($data['data']) > 0) {
                foreach ($data['data'] as $membership) {
                    if (isset($membership['role_name']) && $membership['role_name'] === 'Admin') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function getList(Request $request): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);

            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            // Pass the extracted user ID to the validation function
            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $activeDirection = $request->query('active_direction', 'desc');
            $titleDirection = $request->query('title_direction', 'asc');
            $groupBy = $request->query('group_by', 'none');
            $page = (int) $request->query('page', 1);

            if ($groupBy === 'none') {
                // Use original pagination method
                $list = $this->baseRecipeService->getRecipeList($activeDirection, $titleDirection);
                return response()->json($list);
            } else {
                // Use optimized grouping method with pagination
                $groupedList = $this->baseRecipeService->getRecipeListGrouped(
                    $groupBy,
                    $activeDirection,
                    $titleDirection,
                    $page
                );
                return response()->json($groupedList);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch recipe list: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Failed to fetch recipe list'], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $searchTerm = $request->query('q');
            $activeDirection = $request->query('active_direction', 'desc');
            $titleDirection = $request->query('title_direction', 'asc');
            $groupBy = $request->query('group_by', 'none');
            $page = (int) $request->query('page', 1);

            if ($groupBy === 'none') {
                // Use original pagination method
                $recipes = $this->baseRecipeService->search($searchTerm, $activeDirection, $titleDirection);
                return response()->json($recipes);
            } else {
                // Use optimized grouping method with pagination
                $groupedRecipes = $this->baseRecipeService->searchGrouped(
                    $searchTerm,
                    $groupBy,
                    $activeDirection,
                    $titleDirection,
                    $page
                );
                return response()->json($groupedRecipes);
            }
        } catch (\Exception $e) {
            Log::error('Failed to search recipes: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Failed to search recipes'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);

            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                //'ingredients' => 'nullable|string',
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
                'active' => 'nullable|boolean',
                'recipe_lines' => 'nullable|array',
                'recipe_lines.*.ingredient_name' => 'required_without:recipe_lines.*.ingredient_id|string|max:255',
                'recipe_lines.*.ingredient_id' => 'nullable|exists:ingredients,id',
                'recipe_lines.*.quantity' => 'nullable|numeric',
                'recipe_lines.*.measurement_name' => 'nullable|string|max:255',
                'recipe_lines.*.measurement_id' => 'nullable|exists:measurements,id',
                'recipe_lines.*.sort_order' => 'nullable|integer',
            ]);
            $recipe = $this->baseRecipeService->updateRecipe($id, $validated);
            return response()->json($recipe);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to update meal.'], 500);
        }
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);

            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $this->baseRecipeService->toggleStatus($id);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to toggle status'], 500);
        }
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);

            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $this->baseRecipeService->deleteMeal($id);
            return response()->json(['message' => 'Meal deleted successfully']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to delete meal'], 500);
        }
    }

    public function getRecipes(Request $request): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);

            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $searchTerm = $request->query('q');
            $titleDirection = $request->query('title_direction', 'asc');
            $categoryFilter = $request->query('category');
            $cuisineFilter = $request->query('cuisine');
            $dietaryFilter = $request->query('dietary');

            $recipes = $this->baseRecipeService->getRecipes(
                $searchTerm,
                $titleDirection,
                $categoryFilter,
                $cuisineFilter,
                $dietaryFilter
            );
            return response()->json($recipes);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }
    public function store(Request $request): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);

            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

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
                'active' => 'nullable|boolean',
                'recipe_lines' => 'nullable|array',
                'recipe_lines.*.ingredient_name' => 'required_without:recipe_lines.*.ingredient_id|string|max:255',
                'recipe_lines.*.ingredient_id' => 'nullable|exists:ingredients,id',
                'recipe_lines.*.quantity' => 'nullable|numeric',
                'recipe_lines.*.measurement_name' => 'nullable|string|max:255',
                'recipe_lines.*.measurement_id' => 'nullable|exists:measurements,id',
                'recipe_lines.*.sort_order' => 'nullable|integer',
            ]);

            $recipe = $this->baseRecipeService->createRecipe($validated);
            return response()->json($recipe, 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to create recipe'], 500);
        }
    }
}
