<?php
namespace App\Http\Controllers;

use App\Models\UserMealGroup;
use App\Models\UserMeal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class UserMealGroupsController extends Controller
{
    /**
     * Get all groups for a specific user meal
     */
    public function index(Request $request, $userMealId): JsonResponse
    {
        try {
            $userMeal = UserMeal::findOrFail($userMealId);

            $groups = UserMealGroup::where('user_meal_id', $userMealId)
                ->with(['recipeLines' => function($query) {
                    $query->with('ingredient', 'measurement')->orderBy('sort_order');
                }])
                ->orderBy('sort_order')
                ->get();

            return response()->json([
                'data' => $groups,
                'user_meal' => $userMeal
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user meal groups', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch groups.'], 500);
        }
    }

    /**
     * Create a new group for a user meal
     */
    public function store(Request $request, $userMealId): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:user_meal_groups,name,NULL,id,user_meal_id,' . $userMealId,
                'description' => 'nullable|string|max:500',
                'sort_order' => 'nullable|integer',
            ]);

            $userMeal = UserMeal::findOrFail($userMealId);

            // If no sort_order provided, put it at the end
            if (!isset($validated['sort_order'])) {
                $validated['sort_order'] = UserMealGroup::where('user_meal_id', $userMealId)->count();
            }

            $group = UserMealGroup::create([
                'user_meal_id' => $userMealId,
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
            Log::error('Failed to create user meal group', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to create group.'], 500);
        }
    }

    /**
     * Update an existing group
     */
    public function update(Request $request, $userMealId, $groupId): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:user_meal_groups,name,' . $groupId . ',id,user_meal_id,' . $userMealId,
                'description' => 'nullable|string|max:500',
                'sort_order' => 'nullable|integer',
            ]);

            $group = UserMealGroup::where('user_meal_id', $userMealId)->findOrFail($groupId);
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
            Log::error('Failed to update user meal group', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to update group.'], 500);
        }
    }

    /**
     * Delete a group and move its recipe lines to ungrouped
     */
    public function destroy(Request $request, $userMealId, $groupId): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            $group = UserMealGroup::where('user_meal_id', $userMealId)->findOrFail($groupId);

            // Move all recipe lines to ungrouped (set user_meal_group_id to null)
            $group->recipeLines()->update(['user_meal_group_id' => null]);

            // Delete the group
            $group->delete();

            return response()->json(['message' => 'Group deleted successfully.']);

        } catch (\Exception $e) {
            Log::error('Failed to delete user meal group', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to delete group.'], 500);
        }
    }

    /**
     * Reorder groups
     */
    public function reorder(Request $request, $userMealId): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            $validated = $request->validate([
                'groups' => 'required|array',
                'groups.*.id' => 'required|exists:user_meal_groups,id',
                'groups.*.sort_order' => 'required|integer',
            ]);

            foreach ($validated['groups'] as $groupData) {
                UserMealGroup::where('user_meal_id', $userMealId)
                    ->where('id', $groupData['id'])
                    ->update(['sort_order' => $groupData['sort_order']]);
            }

            return response()->json(['message' => 'Groups reordered successfully.']);

        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Failed to reorder user meal groups', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to reorder groups.'], 500);
        }
    }

    /**
     * Get user meal with all groups and ungrouped lines
     */
    public function getUserMealWithGroups(Request $request, $userMealId): JsonResponse
    {
        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            $userMeal = UserMeal::with([
                'recipeGroups' => function ($query) {
                    $query->ordered()->with(['recipeLines' => function ($lineQuery) {
                        $lineQuery->with('ingredient', 'measurement')->orderBy('sort_order');
                    }]);
                }
            ])->findOrFail($userMealId);

            // Get ungrouped recipe lines using the new scope
            $userMeal->ungrouped_recipe_lines = \App\Models\RecipeLine::where('user_meal_id', $userMealId)
                ->ungroupedUserMeal()
                ->get();

            // Get all recipe lines for convenience
            $userMeal->all_recipe_lines = \App\Models\RecipeLine::where('user_meal_id', $userMealId)
                ->groupedByUserMealGroup()
                ->get();

            return response()->json(['data' => $userMeal]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user meal with groups', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to fetch user meal with groups.'], 500);
        }
    }
}
