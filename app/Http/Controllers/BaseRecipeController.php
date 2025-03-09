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

    public function search(Request $request)
    {
        $searchTerm = $request->query('q');
        $activeDirection = $request->query('active_direction', 'desc');
        $titleDirection = $request->query('title_direction', 'asc');
        $recipes = $this->baseRecipeService->search($searchTerm, $activeDirection, $titleDirection);
        return response()->json($recipes);
    }

    public function getList(Request $request): JsonResponse
    {
        try {
            if (!$this->validateIsAdminWithClerk($request->header('X-User-ID'))) {
                return response()->json(['error' => 'Unauthorized.'], 500);
            }
            $activeDirection = $request->query('active_direction', 'desc');
            $titleDirection = $request->query('title_direction', 'asc');
            $list = $this->baseRecipeService->getRecipeList($activeDirection, $titleDirection);
            return response()->json($list);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            if (!$this->validateIsAdminWithClerk($request->header('X-User-ID'))) {
                return response()->json(['error' => 'Unauthorized.'], 500);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'ingredients' => 'string',
                'instructions' => 'string',
                'cooking_time' => 'nullable|string',
                'serves' => 'nullable|string',
                'dietary' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);

            $recipe = $this->baseRecipeService->createRecipe($validated);
            return response()->json($recipe, 201);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json(['error' => 'Failed to create recipe'], 500);
        }
    }

    public function update(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->validateIsAdminWithClerk($request->header('X-User-ID'))) {
                return response()->json(['error' => 'Unauthorized.'], 500);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'ingredients' => 'nullable|string',
                'instructions' => 'nullable|string',
                'cooking_time' => 'nullable|string',
                'serves' => 'nullable|string',
                'dietary' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'active' => 'nullable|boolean'
            ]);
            $recipe = $this->baseRecipeService->updateRecipe($id, $validated);
            return response()->json($recipe);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
            return response()->json(['error' => 'Failed to update recipe'], 500);
        }
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        try {
            if (!$this->validateIsAdminWithClerk($request->header('X-User-ID'))) {
                return response()->json(['error' => 'Unauthorized.'], 500);
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
            if (!$this->validateIsAdminWithClerk($request->header('X-User-ID'))) {
                return response()->json(['error' => 'Unauthorized.'], 500);
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
            if (!$this->validateIsAdminWithClerk($request->header('X-User-ID'))) {
                return response()->json(['error' => 'Unauthorized.'], 500);
            }

            $searchTerm = $request->query('q');
            $titleDirection = $request->query('title_direction', 'asc');

            $recipes = $this->baseRecipeService->getRecipes($searchTerm, $titleDirection);
            return response()->json($recipes);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to fetch recipes'], 500);
        }
    }
}
