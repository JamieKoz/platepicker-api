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
        $existingMeals = UserMeal::where('user_id', $userId)->exists();

        // Only assign meals if the user doesn't have any yet
        if (!$existingMeals) {
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
        ?string $searchTerm = null
    ): array {
        // If no grouping requested, use the standard pagination method
        if ($groupBy === 'none') {
            $paginator = $searchTerm
                ? $this->search($searchTerm, $activeDirection, $titleDirection)
                : $this->getRecipeList($activeDirection, $titleDirection);

            return $this->formatStandardPagination($paginator);
        }

        // Get groups with counts (filtered by search if applicable)
        $groups = $this->getGroupsWithCounts($groupBy, $searchTerm);

        // Set up pagination for groups
        $groupsPerPage = 5;
        $paginationData = $this->calculateGroupPagination($groups, $page, $groupsPerPage);

        // Get recipes for each group in the current page
        $groupedRecipes = $this->getRecipesForGroups(
            $paginationData['paginatedGroups'],
            $groupBy,
            $activeDirection,
            $titleDirection,
            $searchTerm
        );

        // Generate pagination URLs
        $baseUrl = $searchTerm ? '/api/recipes/search' : '/api/recipes/list';
        $urlParams = [
            'group_by' => $groupBy,
            'active_direction' => $activeDirection,
            'title_direction' => $titleDirection,
        ];

        if ($searchTerm) {
            $urlParams['q'] = $searchTerm;
        }

        $paginationUrls = $this->generatePaginationUrls(
            $baseUrl,
            $urlParams,
            $paginationData['currentPage'],
            $paginationData['totalPages']
        );

        return [
            'grouped' => true,
            'group_by' => $groupBy,
            'groups' => $groupedRecipes,
            'pagination' => array_merge($paginationData['pagination'], $paginationUrls)
        ];
    }

    /**
     * Search recipes with optional grouping (wrapper for backward compatibility)
     */
    public function searchGrouped(
        $searchTerm,
        string $groupBy = 'none',
        string $activeDirection = 'desc',
        string $titleDirection = 'asc',
        int $page = 1,
        int $perPage = 10
    ): array {
        return $this->getRecipeListGrouped(
            $groupBy,
            $activeDirection,
            $titleDirection,
            $page,
            $searchTerm
        );
    }

    /**
     * Get list of groups with recipe counts
     */
    private function getGroupsWithCounts(string $groupBy, ?string $searchTerm = null): array
    {
        $recipeIds = null;

        // If searching, get matching recipe IDs first
        if ($searchTerm) {
            $recipeIds = DB::table('recipes')
                ->where('title', 'ilike', '%' . $searchTerm . '%')
                ->pluck('id')
                ->toArray();

            if (empty($recipeIds)) {
                return [];
            }
        }

        $config = $this->getGroupConfig($groupBy);
        if (!$config) {
            return [];
        }

        // Get groups with counts
        $query = DB::table($config['table'])
            ->select(
                $config['table'] . '.id',
                $config['table'] . '.name',
                DB::raw('COUNT(DISTINCT ' . $config['pivot_table'] . '.' . $config['recipe_column'] . ') as count')
            );

        if ($searchTerm && !empty($recipeIds)) {
            // For search, use INNER JOIN and filter by recipe IDs
            $query->join($config['pivot_table'], $config['table'] . '.id', '=', $config['pivot_table'] . '.' . $config['foreign_column'])
                  ->whereIn($config['pivot_table'] . '.' . $config['recipe_column'], $recipeIds);
        } else {
            // For regular listing, use LEFT JOIN
            $query->leftJoin($config['pivot_table'], $config['table'] . '.id', '=', $config['pivot_table'] . '.' . $config['foreign_column']);
        }

        $groups = $query->groupBy($config['table'] . '.id', $config['table'] . '.name')
                       ->orderBy($config['table'] . '.name')
                       ->get()
                       ->toArray();

        // Add uncategorized count
        $uncategorizedCount = $this->getUncategorizedCount($config, $recipeIds);

        if ($uncategorizedCount > 0) {
            $groups[] = [
                'id' => 0,
                'name' => 'Uncategorized',
                'count' => $uncategorizedCount
            ];
        }

        return array_values($groups);
    }

    /**
     * Get configuration for different group types
     */
    private function getGroupConfig(string $groupBy): ?array
    {
        $configs = [
            'cuisine' => [
                'table' => 'cuisines',
                'pivot_table' => 'recipes_cuisine',
                'foreign_column' => 'cuisine_id',
                'recipe_column' => 'recipe_id',
                'relationship' => 'cuisines'
            ],
            'category' => [
                'table' => 'categories',
                'pivot_table' => 'recipe_categories',
                'foreign_column' => 'category_id',
                'recipe_column' => 'recipe_id',
                'relationship' => 'categories'
            ],
            'dietary' => [
                'table' => 'dietary',
                'pivot_table' => 'recipes_dietary',
                'foreign_column' => 'dietary_id',
                'recipe_column' => 'recipe_id',
                'relationship' => 'dietary'
            ]
        ];

        return $configs[$groupBy] ?? null;
    }

    /**
     * Get count of uncategorized recipes
     */
    private function getUncategorizedCount(array $config, ?array $recipeIds = null): int
    {
        $query = DB::table('recipes')
            ->whereNotExists(function ($subQuery) use ($config) {
                $subQuery->select(DB::raw(1))
                        ->from($config['pivot_table'])
                        ->whereRaw('recipes.id = ' . $config['pivot_table'] . '.' . $config['recipe_column']);
            });

        if ($recipeIds) {
            $query->whereIn('recipes.id', $recipeIds);
        }

        return $query->count();
    }

    /**
     * Calculate pagination data for groups
     */
    private function calculateGroupPagination(array $groups, int $page, int $groupsPerPage): array
    {
        $totalGroups = count($groups);
        $totalPages = ceil($totalGroups / $groupsPerPage);
        $currentPage = min(max(1, $page), max(1, $totalPages));

        $paginatedGroups = array_slice($groups, ($currentPage - 1) * $groupsPerPage, $groupsPerPage);

        return [
            'paginatedGroups' => $paginatedGroups,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $totalPages,
                'per_page' => $groupsPerPage,
                'total_groups' => $totalGroups,
                'from' => (($currentPage - 1) * $groupsPerPage) + 1,
                'to' => min($currentPage * $groupsPerPage, $totalGroups),
            ]
        ];
    }

    /**
     * Get recipes for multiple groups
     */
    private function getRecipesForGroups(
        array $groups,
        string $groupBy,
        string $activeDirection,
        string $titleDirection,
        ?string $searchTerm = null,
        int $recipesPerGroup = 15
    ): array {
        $groupedRecipes = [];

        foreach ($groups as $group) {
            $groupId = is_array($group) ? $group['id'] : $group->id;
            $groupName = is_array($group) ? $group['name'] : $group->name;
            $groupCount = is_array($group) ? $group['count'] : $group->count;

            $recipes = $this->getRecipesForSingleGroup(
                $groupBy,
                $groupId,
                $activeDirection,
                $titleDirection,
                $searchTerm,
                $recipesPerGroup
            );

            $groupedRecipes[] = [
                'id' => $groupId,
                'name' => $groupName,
                'total_recipes' => $groupCount,
                'recipes' => $recipes,
                'has_more' => $groupCount > $recipesPerGroup
            ];
        }

        return $groupedRecipes;
    }

    /**
     * Get recipes for a specific group
     */
    private function getRecipesForSingleGroup(
        string $groupBy,
        $groupId,
        string $activeDirection,
        string $titleDirection,
        ?string $searchTerm = null,
        int $limit = 15
    ): array {
        // Normalize group ID
        if (is_array($groupId)) {
            $groupId = $groupId['id'];
        } else if (is_object($groupId)) {
            $groupId = $groupId->id;
        }
        $groupId = (int)$groupId;

        // Build base query
        $query = Recipe::with(['categories', 'cuisines', 'dietary'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->limit($limit);

        // Add search filter if provided
        if ($searchTerm) {
            $query->where('title', 'ilike', '%' . $searchTerm . '%');
        }

        // Apply group filtering
        $this->applyGroupFilter($query, $groupBy, $groupId);

        return $query->get()->toArray();
    }

    /**
     * Apply group filtering to a query
     */
    private function applyGroupFilter($query, string $groupBy, int $groupId): void
    {
        $config = $this->getGroupConfig($groupBy);
        if (!$config) {
            return;
        }

        if ($groupId === 0) {
            // Handle "Uncategorized" case
            $query->whereDoesntHave($config['relationship']);
        } else {
            // Handle specific group - specify the table name for the id column
            $tableName = $config['table'];
            $query->whereHas($config['relationship'], function ($q) use ($groupId, $tableName) {
                $q->where($tableName . '.id', $groupId);
            });
        }
    }

    /**
     * Format standard pagination response
     */
    private function formatStandardPagination($paginator): array
    {
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

    /**
     * Generate pagination URLs
     */
    private function generatePaginationUrls(
        string $baseUrl,
        array $params,
        int $currentPage,
        int $totalPages
    ): array {
        $prevPageUrl = $currentPage > 1
            ? url($baseUrl) . '?' . http_build_query(array_merge($params, ['page' => $currentPage - 1]))
            : null;

        $nextPageUrl = $currentPage < $totalPages
            ? url($baseUrl) . '?' . http_build_query(array_merge($params, ['page' => $currentPage + 1]))
            : null;

        return [
            'prev_page_url' => $prevPageUrl,
            'next_page_url' => $nextPageUrl,
        ];
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
        return Recipe::where('title', 'ilike', '%' . $searchTerm . '%')
            ->with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function getRecipes($searchTerm = null, string $titleDirection = 'asc', $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null): LengthAwarePaginator
    {
        $query = Recipe::query()->with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);

        if ($searchTerm) {
            $query->where('title', 'ilike', '%' . $searchTerm . '%');
        }

        // Filter by category if provided
        if ($categoryFilter) {
            $query->whereHas('categories', function ($q) use ($categoryFilter) {
                if (is_numeric($categoryFilter)) {
                    $q->where('categories.id', $categoryFilter);
                } else {
                    $q->where('categories.name', 'ilike', '%' . $categoryFilter . '%');
                }
            });
        }

        // Filter by cuisine if provided
        if ($cuisineFilter) {
            $query->whereHas('cuisines', function ($q) use ($cuisineFilter) {
                if (is_numeric($cuisineFilter)) {
                    $q->where('cuisines.id', $cuisineFilter);
                } else {
                    $q->where('cuisines.name', 'ilike', '%' . $cuisineFilter . '%');
                }
            });
        }

        // Filter by dietary if provided
        if ($dietaryFilter) {
            $query->whereHas('dietary', function ($q) use ($dietaryFilter) {
                if (is_numeric($dietaryFilter)) {
                    $q->where('dietary.id', $dietaryFilter);
                } else {
                    $q->where('dietary.name', 'ilike', '%' . $dietaryFilter . '%');
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

    public function showMeal($mealId)
    {
        return Recipe::with([
            'categories',
            'cuisines',
            'dietary',
            'recipeLines.ingredient',
            'recipeLines.measurement'
        ])->findOrFail($mealId);
    }
}
