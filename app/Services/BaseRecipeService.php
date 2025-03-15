<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use App\Models\UserMeal;
use App\Models\Category;
use App\Models\Cuisine;
use App\Models\Dietary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class BaseRecipeService
{
    public function assignInitialMealsToUser(string $userId): void
    {
        // Get 30 random active recipes
        $defaultRecipes = Recipe::where('active', 1)
            ->inRandomOrder()
            ->take(30)
            ->get();

        foreach ($defaultRecipes as $recipe) {
            // Get related data
            $categoryNames = $recipe->categories()->pluck('name')->implode(', ');
            $cuisineNames = $recipe->cuisines()->pluck('name')->implode(', ');
            $dietaryNames = $recipe->dietary()->pluck('name')->implode(', ');

            UserMeal::create([
                'user_id' => $userId,
                'recipe_id' => $recipe->id,
                'active' => true,
                'title' => $recipe->title,
                'ingredients' => $recipe->ingredients,
                'instructions' => $recipe->instructions,
                'image_name' => $recipe->image_name,
                'cooking_time' => $recipe->cooking_time,
                'serves' => $recipe->serves,
                'dietary' => $dietaryNames,
                'cuisine' => $cuisineNames,
                'category' => $categoryNames,
                'cleaned_ingredients' => $recipe->cleaned_ingredients
            ]);
        }
    }

    public function createRecipe(array $data): Recipe
    {
        $recipe = new Recipe();
        $recipe->title = $data['title'];
        $recipe->ingredients = $data['ingredients'];
        $recipe->instructions = $data['instructions'];
        $recipe->cleaned_ingredients = $data['ingredients'];
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

        return $recipe->fresh(['categories', 'cuisines', 'dietary']);
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
            'ingredients' => $data['ingredients'] ?? $recipe->ingredients,
            'instructions' => $data['instructions'] ?? $recipe->instructions,
            'cleaned_ingredients' => $data['ingredients'] ?? $recipe->cleaned_ingredients,
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
        if (isset($data['categories'])) {
            $recipe->categories()->sync($data['categories']);
        }

        if (isset($data['cuisines'])) {
            $recipe->cuisines()->sync($data['cuisines']);
        }

        if (isset($data['dietary'])) {
            $recipe->dietary()->sync($data['dietary']);
        }

        return $recipe->fresh(['categories', 'cuisines', 'dietary']);
    }

    public function toggleStatus($mealId): void
    {
        $recipe = Recipe::where('id', $mealId)->firstOrFail();

        $recipe->active = !$recipe->active;
        $recipe->save();
    }

    public function getRecipeList(string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return Recipe::with(['categories', 'cuisines', 'dietary'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(50);
    }

    public function search($searchTerm, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return Recipe::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->with(['categories', 'cuisines', 'dietary'])
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function deleteMeal(int $mealId): void
    {
        $recipe = Recipe::where('id', $mealId)->firstOrFail();

        // The relationships will be automatically deleted due to onDelete('cascade')
        // in the migration files
        $recipe->delete();
    }

    public function getRecipes($searchTerm = null, string $titleDirection = 'asc', $categoryFilter = null, $cuisineFilter = null, $dietaryFilter = null): LengthAwarePaginator
    {
        $query = Recipe::query()->with(['categories', 'cuisines', 'dietary']);

        if ($searchTerm) {
            $query->where('title', 'LIKE', '%' . $searchTerm . '%');
        }

        // Filter by category if provided
        if ($categoryFilter) {
            $query->whereHas('categories', function($q) use ($categoryFilter) {
                if (is_numeric($categoryFilter)) {
                    $q->where('categories.id', $categoryFilter);
                } else {
                    $q->where('categories.name', 'LIKE', '%' . $categoryFilter . '%');
                }
            });
        }

        // Filter by cuisine if provided
        if ($cuisineFilter) {
            $query->whereHas('cuisines', function($q) use ($cuisineFilter) {
                if (is_numeric($cuisineFilter)) {
                    $q->where('cuisines.id', $cuisineFilter);
                } else {
                    $q->where('cuisines.name', 'LIKE', '%' . $cuisineFilter . '%');
                }
            });
        }

        // Filter by dietary if provided
        if ($dietaryFilter) {
            $query->whereHas('dietary', function($q) use ($dietaryFilter) {
                if (is_numeric($dietaryFilter)) {
                    $q->where('dietary.id', $dietaryFilter);
                } else {
                    $q->where('dietary.name', 'LIKE', '%' . $dietaryFilter . '%');
                }
            });
        }

        return $query->orderBy('title', $titleDirection)->paginate(50);
    }
}
