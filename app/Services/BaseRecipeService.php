<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Measurement;
use App\Models\Recipe;
use App\Models\RecipeLine;
use App\Models\UserMeal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BaseRecipeService
{
    public function assignInitialMealsToUser(string $userId): void
    {
        $defaultRecipes = Recipe::where('active', 1)
            ->take(30)
            ->get();

        foreach ($defaultRecipes as $recipe) {

            UserMeal::firstOrCreate([
                'user_id' => $userId,
                'recipe_id' => $recipe->id,
                'active' => true,
                'title' => $recipe->title,
                'instructions' => $recipe->instructions,
                'image_name' => $recipe->image_name,
                'cooking_time' => $recipe->cooking_time,
                'serves' => $recipe->serves,
            ]);
        }
    }

    public function createRecipe(array $data): Recipe
    {
        $recipe = new Recipe();
        $recipe->title = $data['title'];
        $recipe->instructions = $data['instructions'];
        $recipe->cooking_time = $data['cooking_time'];
        $recipe->serves = $data['serves'];
        $recipe->active = true;

        if (isset($data['image'])) {
            $imageName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $data['image']->getClientOriginalName());
            $imageName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $imageName);
            $data['image']->storeAs('food-images', $imageName, 's3');
            $recipe->image_name = $imageName;
        }

        $recipe->save();

        // Attach relationships
        if (isset($data['categories']) && is_array($data['categories'])) {
            $recipe->categories()->attach($data['categories']);
        }

        if (isset($data['cuisines']) && is_array($data['cuisines'])) {
            $recipe->cuisines()->attach($data['cuisines']);
        }

        if (isset($data['dietary']) && is_array($data['dietary'])) {
            $recipe->dietary()->attach($data['dietary']);
        }

        if (isset($data['recipe_lines']) && is_array($data['recipe_lines'])) {
            $this->saveRecipeLines($recipe, $data['recipe_lines']);
        }
        return $recipe->fresh(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);
    }

    public function updateRecipe(int $id, array $data): Recipe
    {
        $recipe = Recipe::where('id', $id)->firstOrFail();

        if (!$recipe) {
            Log::error('Meal not found', ['meal_id' => $id]);
            throw new \Exception('Meal not found');
        }

        $recipe->fill([
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? $recipe->instructions,
            'cooking_time' => $data['cooking_time'] ?? $recipe->cooking_time,
            'serves' => $data['serves'] ?? $recipe->serves,
        ]);

        if (isset($data['image'])) {
            if ($recipe->image_name) {
                Storage::disk('s3')->delete('food-images/' . $recipe->image_name);
            }
            $imageName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $data['image']->getClientOriginalName());
            $imageName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $imageName);
            $data['image']->storeAs('food-images', $imageName, 's3');
            $recipe->image_name = $imageName;
        }

        $recipe->save();

        // Update relationships
        $recipe->categories()->detach();
        if (isset($data['categories'])) {
            $recipe->categories()->sync($data['categories']);
        }

        $recipe->cuisines()->detach();
        if (isset($data['cuisines'])) {
            $recipe->cuisines()->sync($data['cuisines']);
        }

        $recipe->dietary()->detach();
        if (isset($data['dietary'])) {
            $recipe->dietary()->sync($data['dietary']);
        }

        $recipe->recipeLines()->delete();
        if (isset($data['recipe_lines']) && is_array($data['recipe_lines'])) {
            $this->saveRecipeLines($recipe, $data['recipe_lines']);
        }

        return $recipe->fresh(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);
    }

    /**
     * Save recipe lines for a recipe
     */
    private function saveRecipeLines(Recipe $recipe, array $recipeLines): void
    {
        $sortOrder = 1;

        foreach ($recipeLines as $line) {
            // Handle ingredient - either use provided ID or find/create by name
            if (!empty($line['ingredient_id'])) {
                $ingredientId = $line['ingredient_id'];
            } elseif (!empty($line['ingredient_name'])) {
                // Find or create ingredient by name
                $ingredient = Ingredient::firstOrCreate(['name' => $line['ingredient_name']]);
                $ingredientId = $ingredient->id;
            } else {
                // Skip if no ingredient provided
                continue;
            }

            // Create recipe line
            $recipeLine = new RecipeLine([
                'recipe_id' => $recipe->id,
                'ingredient_id' => $ingredientId,
                'quantity' => $line['quantity'] ?? null,
                'sort_order' => $line['sort_order'] ?? $sortOrder,
            ]);

            // Handle measurement if provided
            if (!empty($line['measurement_id'])) {
                $recipeLine->measurement_id = $line['measurement_id'];
            } elseif (!empty($line['measurement_name'])) {
                // Find or create measurement by name
                $measurement = Measurement::firstOrCreate([
                    'name' => $line['measurement_name']
                ]);
                $recipeLine->measurement_id = $measurement->id;
            }

            $recipeLine->save();
            $sortOrder++;
        }
    }

    public function toggleStatus($mealId): void
    {
        $recipe = Recipe::where('id', $mealId)->firstOrFail();

        $recipe->active = !$recipe->active;
        $recipe->save();
    }

    /**
     * Get recipe list with optional grouping and pagination
     */
    public function getRecipeListGrouped(
        string $groupBy = 'none',
        string $activeDirection = 'desc',
        string $titleDirection = 'asc',
        int $page = 1,
    ): array {
        // If no grouping requested, use the standard pagination method
        if ($groupBy === 'none') {
            $paginator = $this->getRecipeList($activeDirection, $titleDirection);
            return [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ];
        }

        // For grouping, we need a different approach
        // First, get the list of groups and count of recipes in each group
        $groups = $this->getGroupsWithCounts($groupBy);

        // Calculate total groups and set up pagination for groups
        $totalGroups = count($groups);
        $groupsPerPage = 5; // Show 5 groups per page
        $totalPages = ceil($totalGroups / $groupsPerPage);
        $currentPage = min(max(1, $page), max(1, $totalPages));

        // Paginate the groups
        $paginatedGroups = array_slice($groups, ($currentPage - 1) * $groupsPerPage, $groupsPerPage);

        // For each group in the current page, get its recipes
        $groupedRecipes = [];
        foreach ($paginatedGroups as $group) {
            // Get the ID properly regardless of whether it's an array or object
            $groupId = is_array($group) ? $group['id'] : $group->id;
            $groupName = is_array($group) ? $group['name'] : $group->name;
            $groupCount = is_array($group) ? $group['count'] : $group->count;

            // Get recipes for this group with limited relationships
            $recipes = $this->getRecipesForGroup(
                $groupBy,
                $groupId,
                $activeDirection,
                $titleDirection,
                15 // Limit recipes per group to 15
            );

            $groupedRecipes[] = [
                'id' => $groupId,
                'name' => $groupName,
                'total_recipes' => $groupCount,
                'recipes' => $recipes,
                'has_more' => $groupCount > 15
            ];
        }

        // Generate pagination URLs
        $prevPageUrl = $currentPage > 1
            ? url('/api/recipes/list') . '?' . http_build_query([
                'group_by' => $groupBy,
                'active_direction' => $activeDirection,
                'title_direction' => $titleDirection,
                'page' => $currentPage - 1
            ])
            : null;

        $nextPageUrl = $currentPage < $totalPages
            ? url('/api/recipes/list') . '?' . http_build_query([
                'group_by' => $groupBy,
                'active_direction' => $activeDirection,
                'title_direction' => $titleDirection,
                'page' => $currentPage + 1
            ])
            : null;

        // Return the grouped data with pagination info
        return [
            'grouped' => true,
            'group_by' => $groupBy,
            'groups' => $groupedRecipes,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $totalPages,
                'per_page' => $groupsPerPage,
                'total_groups' => $totalGroups,
                'from' => (($currentPage - 1) * $groupsPerPage) + 1,
                'to' => min($currentPage * $groupsPerPage, $totalGroups),
                'prev_page_url' => $prevPageUrl,
                'next_page_url' => $nextPageUrl,
            ]
        ];
    }

    /**
     * Search recipes with optional grouping
     */
    public function searchGrouped(
        $searchTerm,
        string $groupBy = 'none',
        string $activeDirection = 'desc',
        string $titleDirection = 'asc',
        int $page = 1,
        int $perPage = 10
    ): array {
        // If no grouping requested, use the standard search method
        if ($groupBy === 'none') {
            $paginator = $this->search($searchTerm, $activeDirection, $titleDirection);
            return [
                'data' => $paginator->items(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ];
        }

        // For search with grouping, we need to first find matching recipes
        /* $query = Recipe::where('title', 'LIKE', '%' . $searchTerm . '%'); */
        $query = DB::table('recipes')->where('title', 'LIKE', '%' . $searchTerm . '%');

        // Get groups that have recipes matching the search
        $groups = $this->getGroupsWithCountsForSearch($groupBy, $query);

        // Calculate total groups and set up pagination for groups
        $totalGroups = count($groups);
        $groupsPerPage = 5; // Show 5 groups per page
        $totalPages = ceil($totalGroups / $groupsPerPage);
        $currentPage = min(max(1, $page), max(1, $totalPages));

        // Paginate the groups
        $paginatedGroups = array_slice($groups, ($currentPage - 1) * $groupsPerPage, $groupsPerPage);

        // For each group in the current page, get its recipes that match the search
        $groupedRecipes = [];
        foreach ($paginatedGroups as $group) {
            // Get the ID properly regardless of whether it's an array or object
            $groupId = is_array($group) ? $group['id'] : $group->id;
            $groupName = is_array($group) ? $group['name'] : $group->name;
            $groupCount = is_array($group) ? $group['count'] : $group->count;

            // Get recipes for this group with limited relationships
            $recipes = $this->getRecipesForGroupSearch(
                $groupBy,
                $groupId,
                $activeDirection,
                $titleDirection,
                15 // Limit recipes per group to 15
            );

            $groupedRecipes[] = [
                'id' => $groupId,
                'name' => $groupName,
                'total_recipes' => $groupCount,
                'recipes' => $recipes,
                'has_more' => $groupCount > 15
            ];
        }

        // Generate pagination URLs
        $prevPageUrl = $currentPage > 1
            ? url('/api/recipes/search') . '?' . http_build_query([
                'q' => $searchTerm,
                'group_by' => $groupBy,
                'active_direction' => $activeDirection,
                'title_direction' => $titleDirection,
                'page' => $currentPage - 1
            ])
            : null;

        $nextPageUrl = $currentPage < $totalPages
            ? url('/api/recipes/search') . '?' . http_build_query([
                'q' => $searchTerm,
                'group_by' => $groupBy,
                'active_direction' => $activeDirection,
                'title_direction' => $titleDirection,
                'page' => $currentPage + 1
            ])
            : null;

        // Return the grouped data with pagination info
        return [
            'grouped' => true,
            'group_by' => $groupBy,
            'groups' => $groupedRecipes,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $totalPages,
                'per_page' => $groupsPerPage,
                'total_groups' => $totalGroups,
                'from' => (($currentPage - 1) * $groupsPerPage) + 1,
                'to' => min($currentPage * $groupsPerPage, $totalGroups),
                'prev_page_url' => $prevPageUrl,
                'next_page_url' => $nextPageUrl,
            ]
        ];
    }

    /**
     * Get list of groups with recipe counts
     */
    private function getGroupsWithCounts(string $groupBy): array
    {
        switch ($groupBy) {
            case 'cuisine':
                // Get cuisines with recipe counts
                $groups = DB::table('cuisines')
                    ->select('cuisines.id', 'cuisines.name', DB::raw('COUNT(DISTINCT recipes_cuisine.recipe_id) as count'))
                    ->leftJoin('recipes_cuisine', 'cuisines.id', '=', 'recipes_cuisine.cuisine_id')
                    ->groupBy('cuisines.id', 'cuisines.name')
                    ->orderBy('cuisines.name')
                    ->get()
                    ->toArray();

                // Add uncategorized count
                $uncategorizedCount = DB::table('recipes')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('recipes_cuisine')
                            ->whereRaw('recipes.id = recipes_cuisine.recipe_id');
                    })
                    ->count();

                if ($uncategorizedCount > 0) {
                    $groups[] = [
                        'id' => 0,
                        'name' => 'Uncategorized',
                        'count' => $uncategorizedCount
                    ];
                }

                return array_values($groups);

            case 'category':
                // Get categories with recipe counts
                $groups = DB::table('categories')
                    ->select('categories.id', 'categories.name', DB::raw('COUNT(DISTINCT recipe_categories.recipe_id) as count'))
                    ->leftJoin('recipe_categories', 'categories.id', '=', 'recipe_categories.category_id')
                    ->groupBy('categories.id', 'categories.name')
                    ->orderBy('categories.name')
                    ->get()
                    ->toArray();

                // Add uncategorized count
                $uncategorizedCount = DB::table('recipes')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('recipe_categories')
                            ->whereRaw('recipes.id = recipe_categories.recipe_id');
                    })
                    ->count();

                if ($uncategorizedCount > 0) {
                    $groups[] = [
                        'id' => 0,
                        'name' => 'Uncategorized',
                        'count' => $uncategorizedCount
                    ];
                }

                return array_values($groups);

            case 'dietary':
                // Get dietary requirements with recipe counts
                $groups = DB::table('dietary')
                    ->select('dietary.id', 'dietary.name', DB::raw('COUNT(DISTINCT recipes_dietary.recipe_id) as count'))
                    ->leftJoin('recipes_dietary', 'dietary.id', '=', 'recipes_dietary.dietary_id')
                    ->groupBy('dietary.id', 'dietary.name')
                    ->orderBy('dietary.name')
                    ->get()
                    ->toArray();

                // Add uncategorized count
                $uncategorizedCount = DB::table('recipes')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('recipes_dietary')
                            ->whereRaw('recipes.id = recipes_dietary.recipe_id');
                    })
                    ->count();

                if ($uncategorizedCount > 0) {
                    $groups[] = [
                        'id' => 0,
                        'name' => 'Uncategorized',
                        'count' => $uncategorizedCount
                    ];
                }

                return array_values($groups);

            default:
                return [];
        }
    }

    /**
     * Get list of groups with recipe counts for search
     */
    private function getGroupsWithCountsForSearch(string $groupBy, Builder $searchQuery): array
    {
        $recipeIds = $searchQuery->pluck('id')->toArray();

        if (empty($recipeIds)) {
            return [];
        }

        switch ($groupBy) {
            case 'cuisine':
                // Get cuisines with recipe counts for matching recipes
                $groups = DB::table('cuisines')
                    ->select('cuisines.id', 'cuisines.name', DB::raw('COUNT(DISTINCT recipes_cuisine.recipe_id) as count'))
                    ->join('recipes_cuisine', 'cuisines.id', '=', 'recipes_cuisine.cuisine_id')
                    ->whereIn('recipes_cuisine.recipe_id', $recipeIds)
                    ->groupBy('cuisines.id', 'cuisines.name')
                    ->orderBy('cuisines.name')
                    ->get()
                    ->toArray();

                // Add uncategorized count for matching recipes
                $uncategorizedCount = DB::table('recipes')
                    ->whereIn('recipes.id', $recipeIds)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('recipes_cuisine')
                            ->whereRaw('recipes.id = recipes_cuisine.recipe_id');
                    })
                    ->count();

                if ($uncategorizedCount > 0) {
                    $groups[] = [
                        'id' => 0,
                        'name' => 'Uncategorized',
                        'count' => $uncategorizedCount
                    ];
                }

                return array_values($groups);

                // Similar implementations for category and dietary
            case 'category':
                // Get categories with recipe counts for matching recipes
                $groups = DB::table('categories')
                    ->select('categories.id', 'categories.name', DB::raw('COUNT(DISTINCT recipe_categories.recipe_id) as count'))
                    ->join('recipe_categories', 'categories.id', '=', 'recipe_categories.category_id')
                    ->whereIn('recipe_categories.recipe_id', $recipeIds)
                    ->groupBy('categories.id', 'categories.name')
                    ->orderBy('categories.name')
                    ->get()
                    ->toArray();

                // Add uncategorized count for matching recipes
                $uncategorizedCount = DB::table('recipes')
                    ->whereIn('recipes.id', $recipeIds)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('recipe_categories')
                            ->whereRaw('recipes.id = recipe_categories.recipe_id');
                    })
                    ->count();

                if ($uncategorizedCount > 0) {
                    $groups[] = [
                        'id' => 0,
                        'name' => 'Uncategorized',
                        'count' => $uncategorizedCount
                    ];
                }

                return array_values($groups);

            case 'dietary':
                // Get dietary requirements with recipe counts for matching recipes
                $groups = DB::table('dietary')
                    ->select('dietary.id', 'dietary.name', DB::raw('COUNT(DISTINCT recipes_dietary.recipe_id) as count'))
                    ->join('recipes_dietary', 'dietary.id', '=', 'recipes_dietary.dietary_id')
                    ->whereIn('recipes_dietary.recipe_id', $recipeIds)
                    ->groupBy('dietary.id', 'dietary.name')
                    ->orderBy('dietary.name')
                    ->get()
                    ->toArray();

                // Add uncategorized count for matching recipes
                $uncategorizedCount = DB::table('recipes')
                    ->whereIn('recipes.id', $recipeIds)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('recipes_dietary')
                            ->whereRaw('recipes.id = recipes_dietary.recipe_id');
                    })
                    ->count();

                if ($uncategorizedCount > 0) {
                    $groups[] = [
                        'id' => 0,
                        'name' => 'Uncategorized',
                        'count' => $uncategorizedCount
                    ];
                }

                return array_values($groups);

            default:
                return [];
        }
    }

    /**
     * Get recipes for a specific group
     */
    private function getRecipesForGroup(
        string $groupBy,
        $groupId, // Don't enforce a type here to be flexible
        string $activeDirection,
        string $titleDirection,
        int $limit = 15
    ): array {
        // If the group ID is coming as an array key, access it properly
        if (is_array($groupId)) {
            $groupId = $groupId['id'];
        } else if (is_object($groupId)) {
            $groupId = $groupId->id;
        }

        // Ensure it's an integer
        $groupId = (int)$groupId;

        // Rest of your method remains the same
        $query = Recipe::with(['categories', 'cuisines', 'dietary'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->limit($limit);

        // Filter by the appropriate group
        if ($groupId === 0) {
            // Handle the "Uncategorized" case
            switch ($groupBy) {
                case 'cuisine':
                    $query->whereDoesntHave('cuisines');
                    break;
                case 'category':
                    $query->whereDoesntHave('categories');
                    break;
                case 'dietary':
                    $query->whereDoesntHave('dietary');
                    break;
            }
        } else {
            // Handle specific group
            switch ($groupBy) {
                case 'cuisine':
                    $query->whereHas('cuisines', function ($q) use ($groupId) {
                        $q->where('cuisines.id', $groupId);
                    });
                    break;
                case 'category':
                    $query->whereHas('categories', function ($q) use ($groupId) {
                        $q->where('categories.id', $groupId);
                    });
                    break;
                case 'dietary':
                    $query->whereHas('dietary', function ($q) use ($groupId) {
                        $q->where('dietary.id', $groupId);
                    });
                    break;
            }
        }

        return $query->get()->toArray();
    }

    /**
     * Get recipes for a specific group that match a search term
     */
    private function getRecipesForGroupSearch(
        string $groupBy,
        int $groupId,
        string $searchTerm,
        string $activeDirection,
        string $titleDirection,
        int $limit = 15
    ): array {
        $query = Recipe::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->with(['categories', 'cuisines', 'dietary'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->limit($limit);

        // Filter by the appropriate group
        if ($groupId === 0) {
            // Handle the "Uncategorized" case
            switch ($groupBy) {
                case 'cuisine':
                    $query->whereDoesntHave('cuisines');
                    break;
                case 'category':
                    $query->whereDoesntHave('categories');
                    break;
                case 'dietary':
                    $query->whereDoesntHave('dietary');
                    break;
            }
        } else {
            // Handle specific group
            switch ($groupBy) {
                case 'cuisine':
                    $query->whereHas('cuisines', function ($q) use ($groupId) {
                        $q->where('cuisines.id', $groupId);
                    });
                    break;
                case 'category':
                    $query->whereHas('categories', function ($q) use ($groupId) {
                        $q->where('categories.id', $groupId);
                    });
                    break;
                case 'dietary':
                    $query->whereHas('dietary', function ($q) use ($groupId) {
                        $q->where('dietary.id', $groupId);
                    });
                    break;
            }
        }

        return $query->get()->toArray();
    }

    // Keep the original methods for backward compatibility
    public function getRecipeList(string $activeDirection = 'desc', string $titleDirection = 'asc')
    {
        return Recipe::with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(50);
    }

    public function search($searchTerm, string $activeDirection = 'desc', string $titleDirection = 'asc')
    {
        return Recipe::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function getRecipes($searchTerm = null, string $titleDirection = 'asc', $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null): LengthAwarePaginator
    {
        $query = Recipe::query()->with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);

        if ($searchTerm) {
            $query->where('title', 'LIKE', '%' . $searchTerm . '%');
        }

        // Filter by category if provided
        if ($categoryFilter) {
            $query->whereHas('categories', function ($q) use ($categoryFilter) {
                if (is_numeric($categoryFilter)) {
                    $q->where('categories.id', $categoryFilter);
                } else {
                    $q->where('categories.name', 'LIKE', '%' . $categoryFilter . '%');
                }
            });
        }

        // Filter by cuisine if provided
        if ($cuisineFilter) {
            $query->whereHas('cuisines', function ($q) use ($cuisineFilter) {
                if (is_numeric($cuisineFilter)) {
                    $q->where('cuisines.id', $cuisineFilter);
                } else {
                    $q->where('cuisines.name', 'LIKE', '%' . $cuisineFilter . '%');
                }
            });
        }

        // Filter by dietary if provided
        if ($dietaryFilter) {
            $query->whereHas('dietary', function ($q) use ($dietaryFilter) {
                if (is_numeric($dietaryFilter)) {
                    $q->where('dietary.id', $dietaryFilter);
                } else {
                    $q->where('dietary.name', 'LIKE', '%' . $dietaryFilter . '%');
                }
            });
        }

        return $query->orderBy('title', $titleDirection)->paginate(50);
    }


    public function deleteMeal(int $mealId): void
    {
        $recipe = Recipe::where('id', $mealId)->firstOrFail();

        // The relationships will be automatically deleted due to onDelete('cascade')
        // in the migration files
        $recipe->delete();
    }
}
