<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\User;
use App\Models\UserMeal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BaseRecipeService
{
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
                'cooking_time' => $recipe->cooking_time,
                'serves' => $recipe->serves,
                'dietary' => $recipe->dietary,
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
        $recipe->dietary = $data['dietary'];
        $recipe->active = true;

        if (isset($data['image'])) {
            $imageName = preg_replace('/\.(jpg|jpeg|png|gif)$/i', '', $data['image']->getClientOriginalName());
            $imageName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $imageName);
            $data['image']->storeAs('food-images', $imageName, 's3');
            $recipe->image_name = $imageName;
        }

        $recipe->save();
        return $recipe;
    }

    public function updateRecipe(int $id, array $data): Recipe
    {
        $recipe = Recipe::where('id', $id)->firstOrFail();

        if (!$recipe) {
            Log::error('Meal not found', ['meal_id' => $id ]);
            throw new \Exception('Meal not found');
        }
        $recipe->fill([
            'title' => $data['title'],
            'ingredients' => $data['ingredients'] ?? $recipe->ingredients,
            'instructions' => $data['instructions'] ?? $recipe->instructions,
            'cleaned_ingredients' => $data['ingredients'] ?? $recipe->cleaned_ingredients,
            'cooking_time' => $data['cooking_time'] ?? $recipe->cooking_time,
            'serves' => $data['serves'] ?? $recipe->serves,
            'dietary' => $data['dietary'] ?? $recipe->dietary,
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
        return $recipe;
    }

    public function toggleStatus($mealId): void
    {
        $recipe = Recipe::where('id', $mealId)->firstOrFail();

        $recipe->active = !$recipe->active;
        $recipe->save();
    }

    public function getRecipeList(string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return Recipe::orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(50);
    }

    public function search($searchTerm, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return Recipe::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }


    /* public function addFromRecipe(string $authId, int $recipeId): UserMeal */
    /* { */
    /*     $user = User::where('auth_id', $authId)->firstOrFail(); */
    /*     $recipe = Recipe::findOrFail($recipeId); */
    /**/
    /*     return UserMeal::create([ */
    /*         'user_id' => $user->id, */
    /*         'recipe_id' => $recipe->id, */
    /*         'title' => $recipe->title, */
    /*         'ingredients' => $recipe->ingredients, */
    /*         'instructions' => $recipe->instructions, */
    /*         'image_name' => $recipe->image_name, */
    /*         'cleaned_ingredients' => $recipe->cleaned_ingredients, */
    /*         'cooking_time' => $recipe->cooking_time, */
    /*         'serves' => $recipe->serves, */
    /*         'dietary' => $recipe->dietary, */
    /*         'active' => true */
    /*     ]); */
    /* } */

    public function deleteMeal(int $mealId): void
    {
        Recipe::where('id', $mealId)
            ->firstOrFail()
            ->delete();
    }

    public function getRecipes($searchTerm = null, string $titleDirection = 'asc'): LengthAwarePaginator
    {
        $query = Recipe::query();

        if ($searchTerm) {
            $query->where('title', 'LIKE', '%' . $searchTerm . '%');
        }

        return $query->orderBy('title', $titleDirection)->paginate(50);
    }
}



