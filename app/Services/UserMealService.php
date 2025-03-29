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

class UserMealService
{
    public function getRandomRecipesUnauthorized($count = 27, $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null, $cookingTime = null): Collection
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

    public function getRandomRecipesActive($count = 27, $authId, $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null, $cookingTime = null): Collection
    {
        $query = UserMeal::with([
            'categories',
            'cuisines',
            'dietary',
            'recipe',
            'recipeLines.ingredient',
            'recipeLines.measurement'
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
        }

        return $recipes;
    }

    public function createRecipe(array $data, string $authId): UserMeal
    {
        $userMeal = new UserMeal();
        $userMeal->user_id = $authId;
        $userMeal->title = $data['title'];
        $userMeal->instructions = $data['instructions'];
        $userMeal->cleaned_ingredients = $data['ingredients'];
        $userMeal->cooking_time = $data['cooking_time'];
        $userMeal->serves = $data['serves'];
        $userMeal->active = true;

        if (isset($data['image'])) {
            $imageName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $data['image']->getClientOriginalName());
            $imageName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $imageName);
            $data['image']->storeAs('food-images', $imageName, 's3');
            $userMeal->image_name = $imageName;
        }

        $userMeal->save();

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
            'cleaned_ingredients' => $data['ingredients'] ?? $userMeal->cleaned_ingredients,
            'cooking_time' => $data['cooking_time'] ?? $userMeal->cooking_time,
            'serves' => $data['serves'] ?? $userMeal->serves,
        ]);

        if (isset($data['image'])) {
            if ($userMeal->image_name) {
                Storage::disk('s3')->delete('food-images/' . $userMeal->image_name);
            }
            $imageName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $data['image']->getClientOriginalName());
            $imageName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $imageName);
            $data['image']->storeAs('food-images', $imageName, 's3');
            $userMeal->image_name = $imageName;
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
            ->paginate(50);
    }

    public function search($searchTerm, string $authId, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return UserMeal::with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])
            ->where('user_id', $authId)
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function addFromRecipe(string $authId, int $recipeId): UserMeal
    {
        $recipe = Recipe::with(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement'])->findOrFail($recipeId);

        // Create the user meal
        $userMeal = UserMeal::create([
            'user_id' => $authId,
            'recipe_id' => $recipe->id,
            'title' => $recipe->title,
            'ingredients' => $recipe->ingredients,
            'instructions' => $recipe->instructions,
            'image_name' => $recipe->image_name,
            'cleaned_ingredients' => $recipe->cleaned_ingredients,
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
}
