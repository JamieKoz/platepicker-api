<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use App\Models\UserMeal;
use App\Models\UserMealTally;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class RecipeService
{
    public function getRandomRecipesActive($count = 27): Collection
    {
        $recipes = UserMeal::inRandomOrder()->where('active', 1)->take($count)->get();
        foreach ($recipes as $recipe) {
            $recipe->image_url = config('cloudfront.url') . '/food-images/' . $recipe->image_name;
        }
        return $recipes;
    }

    public function assignInitialMealsToUser(User $user): void
    {
        // Get 30 random active recipes
        $defaultRecipes = Recipe::where('active', 1)
            ->inRandomOrder()
            ->take(30)
            ->get();

        foreach ($defaultRecipes as $recipe) {
            UserMeal::create([
                'user_id' => $user->id,
                'recipe_id' => $recipe->id,
                'active' => true,
                'title' => $recipe->title,
                'ingredients' => $recipe->ingredients,
                'instructions' => $recipe->instructions,
                'image_name' => $recipe->image_name,
                'cleaned_ingredients' => $recipe->cleaned_ingredients
            ]);
        }
    }

    public function createRecipe(array $data, string $authId): UserMeal
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        $userMeal = new UserMeal();
        $userMeal->user_id = $user->id;
        $userMeal->title = $data['title'];
        $userMeal->ingredients = $data['ingredients'];
        $userMeal->instructions = $data['instructions'];
        $userMeal->cleaned_ingredients = $data['ingredients'];
        $userMeal->active = true;

        if (isset($data['image'])) {
            $imageName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $data['image']->getClientOriginalName());
            $imageName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $imageName);
            $data['image']->storeAs('food-images', $imageName, 's3');
            $userMeal->image_name = $imageName;
        }

        $userMeal->save();
        return $userMeal;
    }

    public function updateRecipe(int $id, array $data, string $authId): UserMeal
    {
        $user = User::where('auth_id', $authId)->first();
        if (!$user) {
            Log::error('User not found', ['auth_id' => $authId]);
            throw new \Exception('User not found');
        }
        $userMeal = UserMeal::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        if (!$userMeal) {
            Log::error('Meal not found', ['meal_id' => $id, 'user_id' => $user->id]);
            throw new \Exception('Meal not found');
        }
        $userMeal->fill([
            'title' => $data['title'],
            'ingredients' => $data['ingredients'] ?? $userMeal->ingredients,
            'instructions' => $data['instructions'] ?? $userMeal->instructions,
            'cleaned_ingredients' => $data['ingredients'] ?? $userMeal->cleaned_ingredients,
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
        return $userMeal;
    }

    public function toggleStatus(string $authId, $mealId): void
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        $userMeal = UserMeal::where('user_id', $user->id)
            ->where('id', $mealId)
            ->firstOrFail();

        $userMeal->active = !$userMeal->active;
        $userMeal->save();
    }

    public function getRecipeList(string $authId, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        return UserMeal::where('user_id', $user->id)
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(50);
    }

    public function search($searchTerm, string $authId, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        return UserMeal::where('user_id', $user->id)
            ->where('title', 'LIKE', '%' . $searchTerm . '%')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function getRecipes($searchTerm = null, string $titleDirection = 'asc'): LengthAwarePaginator
    {
        $query = Recipe::query();

        if ($searchTerm) {
            $query->where('title', 'LIKE', '%' . $searchTerm . '%');
        }

        return $query->orderBy('title', $titleDirection)->paginate(50);
    }

    public function addFromRecipe(string $authId, int $recipeId): UserMeal
    {
        $user = User::where('auth_id', $authId)->firstOrFail();
        $recipe = Recipe::findOrFail($recipeId);

        return UserMeal::create([
            'user_id' => $user->id,
            'recipe_id' => $recipe->id,
            'title' => $recipe->title,
            'ingredients' => $recipe->ingredients,
            'instructions' => $recipe->instructions,
            'image_name' => $recipe->image_name,
            'cleaned_ingredients' => $recipe->cleaned_ingredients,
            'active' => true
        ]);
    }

    public function deleteMeal(string $authId, int $mealId): void
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        UserMeal::where('id', $mealId)
            ->where('user_id', $user->id)
            ->firstOrFail()
            ->delete();
    }

    public function incrementMealTally(string $authId, int $mealId): void
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        $tally = UserMealTally::firstOrNew([
            'user_id' => $user->id,
            'recipe_id' => $mealId
        ]);

        if (!$tally->exists) {
            $tally->tally = 1;
        } else {
            $tally->tally = $tally->tally + 1;
        }

        $tally->last_selected_at = now();
        $tally->save();
    }
}
