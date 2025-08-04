<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\UserMeal;
use App\Models\Ingredient;
use App\Models\Measurement;
use App\Models\RecipeLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class UserMealService
{
    private const GROUPS_PER_PAGE = 5;
    private const USER_MEALS_PER_GROUP = 15;
    private const USER_MEALS_PER_PAGE = 50;
    private const SEARCH_PER_PAGE = 10;

    public function getRandomRecipesUnauthorized($count = 17, $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null, $cookingTime = null): Collection
    {
        $query = Recipe::query()
            ->with(['categories', 'cuisines', 'dietary'])
            ->where('active', 1);

        // Apply category filter if provided
        if ($categoryFilter) {
            $categoryIds = explode(',', $categoryFilter);
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // Apply cuisine filter if provided
        if ($cuisineFilter) {
            $cuisineIds = explode(',', $cuisineFilter);
            $query->whereHas('cuisines', function ($q) use ($cuisineIds) {
                $q->whereIn('cuisines.id', $cuisineIds);
            });
        }

        // Apply dietary filter if provided
        if ($dietaryFilter) {
            $dietaryIds = explode(',', $dietaryFilter);
            $query->whereHas('dietary', function ($q) use ($dietaryIds) {
                $q->whereIn('dietary.id', $dietaryIds);
            });
        }

        if ($cookingTime) {
            $query->where(function ($q) use ($cookingTime) {
                $q->where('cooking_time', '<=', $cookingTime)
                    ->orWhereNull('cooking_time');
            });
        }

        $recipes = $query->inRandomOrder()->take($count)->get();

        foreach ($recipes as $recipe) {
            $recipe->image_url = config('cloudfront.url') . '/food-images/' . $recipe->image_name;
        }

        return $recipes;
    }

    public function getRandomRecipesActive($authId, $count = 17, $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null, $cookingTime = null): Collection
    {
        $query = UserMeal::with([
            'categories',
            'cuisines',
            'dietary',
            'recipe',
            'recipeLines.ingredient',
            'recipeLines.measurement',
            'userMealGroups'
        ])
        ->select('user_meals.*')
        ->where('user_meals.active', 1)
        ->where('user_meals.user_id', $authId);

        // Apply category filter if provided
        if ($categoryFilter) {
            $categoryIds = explode(',', $categoryFilter);
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        // Apply cuisine filter if provided
        if ($cuisineFilter) {
            $cuisineIds = explode(',', $cuisineFilter);
            $query->whereHas('cuisines', function ($q) use ($cuisineIds) {
                $q->whereIn('cuisines.id', $cuisineIds);
            });
        }

        // Apply dietary filter if provided
        if ($dietaryFilter) {
            $dietaryIds = explode(',', $dietaryFilter);
            $query->whereHas('dietary', function ($q) use ($dietaryIds) {
                $q->whereIn('dietary.id', $dietaryIds);
            });
        }

        if ($cookingTime) {
            $query->where(function ($q) use ($cookingTime) {
                $q->where('cooking_time', '<=', $cookingTime)
                    ->orWhereNull('cooking_time');
            });
        }

        $recipes = $query->inRandomOrder()->take($count)->get();

        foreach ($recipes as $recipe) {
            $recipe->image_url = config('cloudfront.url') . '/food-images/' . $recipe->image_name;
            $recipe->user_meal_groups = $recipe->userMealGroups;
        }

        return $recipes;
    }

    public function createRecipe(array $data, string $authId): UserMeal
    {
        $userMeal = new UserMeal();
        $userMeal->user_id = $authId;
        $userMeal->title = $data['title'];
        $userMeal->instructions = $data['instructions'];
        $userMeal->cooking_time = $data['cooking_time'];
        $userMeal->serves = $data['serves'];
        $userMeal->active = true;
        if (isset($data['image'])) {
            // For new user meals, generate a temporary name that we'll update after save
            $tempName = 'temp-' . uniqid();
            $extension = $data['image']->getClientOriginalExtension();
            $tempFullName = $tempName . '.' . $extension;

            $data['image']->storeAs('user-meal-images', $tempFullName, 's3');
            $userMeal->image_name = $tempName;
        }

        $userMeal->save();

        // After save, update with proper name if image was uploaded
        if (isset($data['image'])) {
            $properName = 'user-meal-' . $userMeal->id . '-' . time();
            $extension = $data['image']->getClientOriginalExtension();

            // Copy to proper name and delete temp
            Storage::disk('s3')->copy(
                'user-meal-images/' . $userMeal->image_name . '.' . $extension,
                'user-meal-images/' . $properName . '.' . $extension
            );
            Storage::disk('s3')->delete('user-meal-images/' . $userMeal->image_name . '.' . $extension);

            $userMeal->image_name = $properName;
            $userMeal->save();
        }

        // Attach relationships
        if (isset($data['categories']) && is_array($data['categories'])) {
            $userMeal->categories()->attach($data['categories']);
        }

        if (isset($data['cuisines']) && is_array($data['cuisines'])) {
            $userMeal->cuisines()->attach($data['cuisines']);
        }

        if (isset($data['dietary']) && is_array($data['dietary'])) {
            $userMeal->dietary()->attach($data['dietary']);
        }

        if (isset($data['recipe_lines']) && is_array($data['recipe_lines'])) {
            $this->saveRecipeLines($userMeal, $data['recipe_lines']);
        }
        return $userMeal->fresh(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);
    }

    public function updateRecipe(int $id, array $data, string $authId): UserMeal
    {
        $userMeal = UserMeal::where('id', $id)
            ->where('user_id', $authId)
            ->firstOrFail();

        if (!$userMeal) {
            Log::error('Meal not found', ['meal_id' => $id, 'user_id' => $authId]);
            throw new \Exception('Meal not found');
        }

        $userMeal->fill([
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? $userMeal->instructions,
            'cooking_time' => $data['cooking_time'] ?? $userMeal->cooking_time,
            'serves' => $data['serves'] ?? $userMeal->serves,
        ]);

        if (isset($data['image'])) {
            // Delete old image if it exists
            if ($userMeal->image_name) {
                Storage::disk('s3')->delete('user-meal-images/' . $userMeal->image_name);
            }

            // Generate unique filename for user meals
            $imageName = 'user-meal-' . $userMeal->id . '-' . time();
            $extension = $data['image']->getClientOriginalExtension();
            $fullImageName = $imageName . '.' . $extension;

            // Store in user-meal-images folder
            $data['image']->storeAs('user-meal-images', $fullImageName, 's3');
            $userMeal->image_name = $imageName; // Store without extension
        }

        $userMeal->save();

        // Update relationships
        $userMeal->categories()->detach();
        if (isset($data['categories'])) {
            $userMeal->categories()->sync($data['categories']);
        }

        $userMeal->cuisines()->detach();
        if (isset($data['cuisines'])) {
            $userMeal->cuisines()->sync($data['cuisines']);
        }

        $userMeal->dietary()->detach();
        if (isset($data['dietary'])) {
            $userMeal->dietary()->sync($data['dietary']);
        }
        $userMeal->recipeLines()->delete();

        if (isset($data['recipe_lines']) && is_array($data['recipe_lines'])) {
            $this->saveRecipeLines($userMeal, $data['recipe_lines']);
        }
        return $userMeal->fresh(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);
    }

    private function saveRecipeLines(UserMeal $userMeal, array $recipeLines): void
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
                'user_meal_id' => $userMeal->id,
                'ingredient_id' => $ingredientId,
                'quantity' => $line['quantity'] ?? null,
                'sort_order' => $line['sort_order'] ?? $sortOrder,
                'user_meal_group_id' => !empty($line['user_meal_group_id']) ? $line['user_meal_group_id'] : null,
                'notes' => $line['notes'] ?? null,
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

    public function toggleStatus(string $authId, $mealId): void
    {
        $userMeal = UserMeal::where('user_id', $authId)
            ->where('id', $mealId)
            ->firstOrFail();

        $userMeal->active = !$userMeal->active;
        $userMeal->save();
    }

    public function getRecipeList(string $authId, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return UserMeal::with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])
        ->where('user_id', $authId)
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(self::USER_MEALS_PER_PAGE);
    }

    public function search($searchTerm, string $authId, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return UserMeal::with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])
            ->where('user_id', $authId)
            ->where('title', 'ILIKE', '%' . $searchTerm . '%')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(self::SEARCH_PER_PAGE);
    }

    public function addFromRecipe(string $authId, int $recipeId): UserMeal
    {
        $recipe = Recipe::with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])->findOrFail($recipeId);

        // Create the user meal
        $userMeal = UserMeal::create([
            'user_id' => $authId,
            'recipe_id' => $recipe->id,
            'title' => $recipe->title,
            'instructions' => $recipe->instructions,
            'image_name' => $recipe->image_name,
            'cooking_time' => $recipe->cooking_time,
            'serves' => $recipe->serves,
            'active' => true
        ]);

        // Copy categories
        if ($recipe->categories && $recipe->categories->count() > 0) {
            $categoryIds = $recipe->categories->pluck('id')->toArray();
            $userMeal->categories()->attach($categoryIds);
        }

        // Copy cuisines
        if ($recipe->cuisines && $recipe->cuisines->count() > 0) {
            $cuisineIds = $recipe->cuisines->pluck('id')->toArray();
            $userMeal->cuisines()->attach($cuisineIds);
        }

        // Copy dietary requirements
        if ($recipe->dietary && $recipe->dietary->count() > 0) {
            $dietaryIds = $recipe->dietary->pluck('id')->toArray();
            $userMeal->dietary()->attach($dietaryIds);
        }
        // Copy recipe lines
        if ($recipe->recipeLines && $recipe->recipeLines->count() > 0) {
            foreach ($recipe->recipeLines as $recipeLine) {
                RecipeLine::create([
                    'user_meal_id' => $userMeal->id,
                    'ingredient_id' => $recipeLine->ingredient_id,
                    'quantity' => $recipeLine->quantity,
                    'measurement_id' => $recipeLine->measurement_id,
                    'notes' => $recipeLine->notes,
                    'sort_order' => $recipeLine->sort_order
                ]);
            }
        }
        return $userMeal->fresh(['categories', 'cuisines', 'dietary']);
    }

    public function deleteMeal(string $authId, int $mealId): void
    {
        UserMeal::where('id', $mealId)
            ->where('user_id', $authId)
            ->firstOrFail()
            ->delete();
    }

    public function getRecipeListGrouped(
        string $groupBy = 'none',
        string $activeDirection = 'desc',
        string $titleDirection = 'asc',
        int $page = 1,
    ): array {
        // If no grouping requested, use the standard pagination method
        if ($groupBy === 'none') {
            $paginator = $this->getRecipeList($activeDirection, $titleDirection);
            return $this->formatPaginatorResponse($paginator);
        }

        // For grouping, use the generic grouped response handler
        return $this->getGroupedResponse(
            groupBy: $groupBy,
            activeDirection: $activeDirection,
            titleDirection: $titleDirection,
            page: $page,
            searchTerm: null,
            endpoint: '/api/user-meals/list'
        );
    }

    /**
     * Search user_meals with optional grouping
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
            return $this->formatPaginatorResponse($paginator);
        }

        // For search with grouping, use the generic grouped response handler
        return $this->getGroupedResponse(
            groupBy: $groupBy,
            activeDirection: $activeDirection,
            titleDirection: $titleDirection,
            page: $page,
            searchTerm: $searchTerm,
            endpoint: '/api/user-meals/search'
        );
    }

    /**
     * Get list of groups with recipe counts
     */
     private function getGroupedResponse(
        string $groupBy,
        string $activeDirection,
        string $titleDirection,
        int $page,
        ?string $searchTerm,
        string $endpoint
    ): array {
        // Get groups based on whether we're searching or not
        $groups = $searchTerm
            ? $this->getGroupsWithCounts($groupBy, $searchTerm)
            : $this->getGroupsWithCounts($groupBy);

        // Calculate pagination
        $totalGroups = count($groups);
        $totalPages = ceil($totalGroups / self::GROUPS_PER_PAGE);
        $currentPage = min(max(1, $page), max(1, $totalPages));

        // Paginate the groups
        $paginatedGroups = array_slice($groups, ($currentPage - 1) * self::GROUPS_PER_PAGE, self::GROUPS_PER_PAGE);

        // Build grouped user meals
        $groupedUserMeals = [];
        foreach ($paginatedGroups as $group) {
            $groupId = $this->extractGroupId($group);
            $groupName = $this->extractGroupProperty($group, 'name');
            $groupCount = $this->extractGroupProperty($group, 'count');

            $userMeals = $this->getUserMealsForGroup(
                groupBy: $groupBy,
                groupId: $groupId,
                activeDirection: $activeDirection,
                titleDirection: $titleDirection,
                searchTerm: $searchTerm,
                limit: self::USER_MEALS_PER_GROUP
            );

            $groupedUserMeals[] = [
                'id' => $groupId,
                'name' => $groupName,
                'total_user_meals' => $groupCount,
                'user_meals' => $userMeals,
                'has_more' => $groupCount > self::USER_MEALS_PER_GROUP
            ];
        }

        // Generate pagination URLs
        $queryParams = [
            'group_by' => $groupBy,
            'active_direction' => $activeDirection,
            'title_direction' => $titleDirection,
        ];

        if ($searchTerm) {
            $queryParams['q'] = $searchTerm;
        }

        return [
            'grouped' => true,
            'group_by' => $groupBy,
            'groups' => $groupedUserMeals,
            'pagination' => $this->buildGroupPagination(
                currentPage: $currentPage,
                totalPages: $totalPages,
                totalGroups: $totalGroups,
                endpoint: $endpoint,
                queryParams: $queryParams
            )
        ];
    }

    /**
     * Get list of groups with recipe counts for search
     */
      private function getGroupsWithCounts(string $groupBy, ?string $searchTerm = null): array
    {
        $groupConfigs = [
            'cuisine' => [
                'table' => 'cuisines',
                'joinTable' => 'user_meals_cuisine',
                'foreignKey' => 'cuisine_id',
                'userMealKey' => 'user_meal_id'
            ],
            'category' => [
                'table' => 'categories',
                'joinTable' => 'user_meals_categories',
                'foreignKey' => 'category_id',
                'userMealKey' => 'user_meal_id'
            ],
            'dietary' => [
                'table' => 'dietary',
                'joinTable' => 'user_meals_dietary',
                'foreignKey' => 'dietary_id',
                'userMealKey' => 'user_meals_id'
            ]
        ];

        if (!isset($groupConfigs[$groupBy])) {
            return [];
        }

        $config = $groupConfigs[$groupBy];

        // If searching, get filtered user meal IDs first
        $userMealIds = null;
        if ($searchTerm) {
            $userMealIds = DB::table('user_meals')
                ->where('title', 'ILIKE', '%' . $searchTerm . '%')
                ->pluck('id')
                ->toArray();

            if (empty($userMealIds)) {
                return [];
            }
        }

        // Get grouped counts
        $groups = $this->getGroupedCounts($config, $userMealIds);

        // Add uncategorized count
        $uncategorizedCount = $this->getUncategorizedCount($config['joinTable'], $userMealIds);

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
     * Get user_meals for a specific group
     */
     private function getGroupedCounts(array $config, ?array $userMealIds = null): array
    {
        $query = DB::table($config['table'])
            ->select(
                $config['table'] . '.id',
                $config['table'] . '.name',
                DB::raw('COUNT(DISTINCT ' . $config['joinTable'] . '.' . $config['userMealKey'] . ') as count')
            )
            ->leftJoin($config['joinTable'], $config['table'] . '.id', '=', $config['joinTable'] . '.' . $config['foreignKey'])
            ->groupBy($config['table'] . '.id', $config['table'] . '.name')
            ->orderBy($config['table'] . '.name');

        if ($userMealIds !== null) {
            $query->whereIn($config['joinTable'] . '.' . $config['userMealKey'], $userMealIds);
        }

        return $query->get()->toArray();
    }

    /**
     * Get recipes for a specific group that match a search term
     */
    private function getUncategorizedCount(string $joinTable, ?array $userMealIds = null): int
    {
        // Determine the correct column name based on the join table
        $userMealColumn = match ($joinTable) {
            'user_meals_dietary' => 'user_meals_id',
            'user_meals_cuisine' => 'user_meal_id',
            'user_meals_categories' => 'user_meal_id',
            default => 'user_meal_id'
        };

        $query = DB::table('user_meals')
        ->whereNotExists(function ($query) use ($joinTable, $userMealColumn) {
            $query->select(DB::raw(1))
                ->from($joinTable)
                ->whereRaw('user_meals.id = ' . $joinTable . '.' . $userMealColumn);
        });

        if ($userMealIds !== null) {
            $query->whereIn('user_meals.id', $userMealIds);
        }

        return $query->count();
    }

    public function getUserMeals($searchTerm = null, string $titleDirection = 'asc', $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null): LengthAwarePaginator
    {
        $query = UserMeal::query()->with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);

        if ($searchTerm) {
            $query->where('title', 'ILIKE', '%' . $searchTerm . '%');
        }

        // Filter by category if provided
        if ($categoryFilter) {
            $query->whereHas('categories', function ($q) use ($categoryFilter) {
                if (is_numeric($categoryFilter)) {
                    $q->where('categories.id', $categoryFilter);
                } else {
                    $q->where('categories.name', 'ILIKE', '%' . $categoryFilter . '%');
                }
            });
        }

        // Filter by cuisine if provided
        if ($cuisineFilter) {
            $query->whereHas('cuisines', function ($q) use ($cuisineFilter) {
                if (is_numeric($cuisineFilter)) {
                    $q->where('cuisines.id', $cuisineFilter);
                } else {
                    $q->where('cuisines.name', 'ILIKE', '%' . $cuisineFilter . '%');
                }
            });
        }

        // Filter by dietary if provided
        if ($dietaryFilter) {
            $query->whereHas('dietary', function ($q) use ($dietaryFilter) {
                if (is_numeric($dietaryFilter)) {
                    $q->where('dietary.id', $dietaryFilter);
                } else {
                    $q->where('dietary.name', 'ILIKE', '%' . $dietaryFilter . '%');
                }
            });
        }

        return $query->orderBy('title', $titleDirection)->paginate(self::USER_MEALS_PER_PAGE);
    }


  private function getUserMealsForGroup(
        string $groupBy,
        int $groupId,
        string $activeDirection,
        string $titleDirection,
        ?string $searchTerm = null,
        int $limit = 15
    ): array {
        $query = UserMeal::with(['categories', 'cuisines', 'dietary'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->limit($limit);

        // Add search condition if provided
        if ($searchTerm) {
            $query->where('title', 'ILIKE', '%' . $searchTerm . '%');
        }

        // Apply group filter
        $this->applyGroupFilter($query, $groupBy, $groupId);

        return $query->get()->toArray();
    }

    /**
     * Apply group filter to query
     */
    private function applyGroupFilter($query, string $groupBy, int $groupId): void
    {
        if ($groupId === 0) {
            // Handle uncategorized
            $relationMap = [
                'cuisine' => 'cuisines',
                'category' => 'categories',
                'dietary' => 'dietary'
            ];

            if (isset($relationMap[$groupBy])) {
                $query->whereDoesntHave($relationMap[$groupBy]);
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
    }
  private function formatPaginatorResponse(LengthAwarePaginator $paginator): array
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
     * Build group pagination data
     */
    private function buildGroupPagination(
        int $currentPage,
        int $totalPages,
        int $totalGroups,
        string $endpoint,
        array $queryParams
    ): array {
        $prevPageUrl = null;
        $nextPageUrl = null;

        if ($currentPage > 1) {
            $prevPageUrl = url($endpoint) . '?' . http_build_query(
                array_merge($queryParams, ['page' => $currentPage - 1])
            );
        }

        if ($currentPage < $totalPages) {
            $nextPageUrl = url($endpoint) . '?' . http_build_query(
                array_merge($queryParams, ['page' => $currentPage + 1])
            );
        }

        return [
            'current_page' => $currentPage,
            'last_page' => $totalPages,
            'per_page' => self::GROUPS_PER_PAGE,
            'total_groups' => $totalGroups,
            'from' => (($currentPage - 1) * self::GROUPS_PER_PAGE) + 1,
            'to' => min($currentPage * self::GROUPS_PER_PAGE, $totalGroups),
            'prev_page_url' => $prevPageUrl,
            'next_page_url' => $nextPageUrl,
        ];
    }

    /**
     * Extract group ID from various formats
     */
    private function extractGroupId($group): int
    {
        if (is_array($group)) {
            return (int) $group['id'];
        } elseif (is_object($group)) {
            return (int) $group->id;
        }
        return (int) $group;
    }
    private function extractGroupProperty($group, string $property)
    {
        if (is_array($group)) {
            return $group[$property];
        } elseif (is_object($group)) {
            return $group->$property;
        }
        return null;
    }

    public function showMeal($mealId)
    {
        $userMeal = UserMeal::with([
            'categories',
            'cuisines',
            'dietary',
            'recipeLines.ingredient',
            'recipeLines.measurement',
            'userMealGroups' => function ($query) {
                $query->orderBy('sort_order');
            }
        ])->findOrFail($mealId);

        $userMeal->recipe_groups = $userMeal->userMealGroups;

        unset($userMeal->userMealGroups);

        return $userMeal;
    }
}
