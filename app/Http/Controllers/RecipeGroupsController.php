<?php
namespace App\Http\Controllers;

use App\Models\RecipeGroup;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class RecipeGroupsController extends Controller
{
    protected $apiKey;
    protected $clerkUrl;

    public function __construct()
    {
        $this->apiKey = env('CLERK_API_KEY');
        $this->clerkUrl = env('CLERK_API_URL', 'https://api.clerk.com/v1');
    }

    /**
     * Get all groups for a specific recipe
     */
    public function index(Request $request, $recipeId): JsonResponse
    {
        try {
            $recipe = Recipe::findOrFail($recipeId);

            $groups = RecipeGroup::where('recipe_id', $recipeId)
                ->with(['recipeLines' => function($query) {
                    $query->with('ingredient', 'measurement')->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'data' => $groups,
                'recipe' => $recipe
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch recipe groups', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch groups.'], 500);
        }
    }

    /**
     * Create a new group for a recipe
     */
    public function store(Request $request, $recipeId): JsonResponse
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
                'name' => 'required|string|max:100|unique:recipe_groups,name,NULL,id,recipe_id,' . $recipeId,
                'description' => 'nullable|string|max:500',
                'sort_order' => 'nullable|integer',
            ]);

            $recipe = Recipe::findOrFail($recipeId);

            // If no sort_order provided, put it at the end
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = RecipeGroup::where('recipe_id', $recipeId)->count();
            }

            $group = RecipeGroup::create([
                'recipe_id' => $recipeId,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'sort_order' => $validated['sort_order'],
            ]);

            return response()->json([
                'data' => $group->load(['recipeLines' => function($query) {
                    $query->with('ingredient', 'measurement')->orderBy('sort_order');
                }]),
                'message' => 'Group created successfully.'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create recipe group', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create group.'], 500);
        }
    }

    /**
     * Update an existing group
     */
    public function update(Request $request, $recipeId, $groupId): JsonResponse
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
                'name' => 'required|string|max:100|unique:recipe_groups,name,' . $groupId . ',id,recipe_id,' . $recipeId,
                'description' => 'nullable|string|max:500',
                'sort_order' => 'nullable|integer',
            ]);

            $group = RecipeGroup::where('recipe_id', $recipeId)->findOrFail($groupId);
            $group->update($validated);

            return response()->json([
                'data' => $group->load(['recipeLines' => function($query) {
                    $query->with('ingredient', 'measurement')->orderBy('sort_order');
                }]),
                'message' => 'Group updated successfully.'
            ]);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update recipe group', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update group.'], 500);
        }
    }

    /**
     * Delete a group and move its recipe lines to ungrouped
     */
    public function destroy(Request $request, $recipeId, $groupId): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            if (!$this->validateIsAdminWithClerk($userData['id'])) {
                return response()->json(['error' => 'Unauthorized.'], 403);
            }

            $group = RecipeGroup::where('recipe_id', $recipeId)->findOrFail($groupId);

            // Move all recipe lines to ungrouped (set recipe_group_id to null)
            $group->recipeLines()->update(['recipe_group_id' => null]);

            // Delete the group
            $group->delete();

            return response()->json(['message' => 'Group deleted successfully.']);

        } catch (\Exception $e) {
            Log::error('Failed to delete recipe group', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete group.'], 500);
        }
    }

    /**
     * Reorder groups
     */
    public function reorder(Request $request, $recipeId): JsonResponse
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
                'groups' => 'required|array',
                'groups.*.id' => 'required|exists:recipe_groups,id',
                'groups.*.sort_order' => 'required|integer',
            ]);

            foreach ($validated['groups'] as $groupData) {
                RecipeGroup::where('recipe_id', $recipeId)
                    ->where('id', $groupData['id'])
                    ->update(['sort_order' => $groupData['sort_order']]);
            }

            return response()->json(['message' => 'Groups reordered successfully.']);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to reorder recipe groups', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to reorder groups.'], 500);
        }
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
}
