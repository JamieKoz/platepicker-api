<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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
}
