<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use App\Models\UserMeal;
use App\Models\UserMealTally;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\Dietary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UserMealService
{
    public function getRandomRecipesUnauthorized($count = 27, $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null): Collection
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

        $recipes = $query->inRandomOrder()->take($count)->get();

        foreach ($recipes as $recipe) {
            $recipe->image_url = config('cloudfront.url') . '/food-images/' . $recipe->image_name;
        }

        return $recipes;
    }

    public function getRandomRecipesActive($count = 27, $authId, $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null): Collection
    {
        $query = UserMeal::with(['categories', 'cuisines', 'dietary', 'recipe'])
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
        $userMeal->ingredients = $data['ingredients'];
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

        return $userMeal->fresh(['categories', 'cuisines', 'dietary']);
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
            'ingredients' => $data['ingredients'] ?? $userMeal->ingredients,
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
        if (isset($data['categories'])) {
            $userMeal->categories()->sync($data['categories']);
        }

        if (isset($data['cuisines'])) {
            $userMeal->cuisines()->sync($data['cuisines']);
        }

        if (isset($data['dietary'])) {
            $userMeal->dietary()->sync($data['dietary']);
        }

        return $userMeal->fresh(['categories', 'cuisines', 'dietary']);
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
        return UserMeal::with(['categories', 'cuisines', 'dietary'])
            ->where('user_id', $authId)
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(50);
    }

    public function search($searchTerm, string $authId, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return UserMeal::with(['categories', 'cuisines', 'dietary'])
            ->where('user_id', $authId)
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function addFromRecipe(string $authId, int $recipeId): UserMeal
    {
        $recipe = Recipe::with(['categories', 'cuisines', 'dietary'])->findOrFail($recipeId);

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

        // Copy relationships from recipe to user meal

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
