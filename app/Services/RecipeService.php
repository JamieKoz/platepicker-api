<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class RecipeService
{
    public function getRandomRecipesActive($count = 27): Collection
    {
        $recipes = Recipe::inRandomOrder()->where('active', 1)->take($count)->get();
        foreach ($recipes as $recipe) {
            $recipe->image_url = config('cloudfront.url') . '/food-images/' . $recipe->image_name;
        }
        return $recipes;
    }

    public function getRecipeList(string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return Recipe::whereNotNull('title')
            ->where('title', '!=', '')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(25);
    }

    public function search($searchTerm, string $activeDirection = 'desc', string $titleDirection = 'asc'): LengthAwarePaginator
    {
        return Recipe::where('title', 'LIKE', '%' . $searchTerm . '%')
            ->orderBy('active', $activeDirection)
            ->orderBy('title', $titleDirection)
            ->paginate(10);
    }

    public function toggleStatus($mealId): Recipe
    {
        $recipe = Recipe::findOrFail($mealId);
        $recipe->active = !$recipe->active;
        $recipe->save();
        return $recipe;
    }

    public function createRecipe(array $data): Recipe
    {
        $recipe = new Recipe();
        $recipe->title = $data['title'];
        $recipe->ingredients = $data['ingredients'];
        $recipe->instructions = $data['instructions'];
        $recipe->cleaned_ingredients = $data['ingredients'];
        $recipe->active = $data['active'] ?? true;

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
        $recipe = Recipe::findOrFail($id);
        $recipe->title = $data['title'];
        $recipe->ingredients = $data['ingredients'] ?? $recipe->ingredients;
        $recipe->instructions = $data['instructions'] ?? $recipe->instructions;
        $recipe->cleaned_ingredients = $data['ingredients'] ?? $recipe->cleaned_ingredients;
        $recipe->active = $data['active'] ?? $recipe->active;

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
}
