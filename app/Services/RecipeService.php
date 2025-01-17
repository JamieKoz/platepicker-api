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

    public function getRecipeList(): LengthAwarePaginator
    {
        return Recipe::whereNotNull('title')->where('title', '!=', '')->orderBy('title', 'asc')->paginate(25);
    }

    public function toggleStatus($mealId): Recipe
    {
        $recipe = Recipe::findOrFail($mealId);
        $recipe->active = !$recipe->active;
        $recipe->save();
        return $recipe;
    }

    public function search($searchTerm): LengthAwarePaginator
    {
        return Recipe::orderBy('title', 'ASC')->where('title', 'LIKE', '%' . $searchTerm . '%')->paginate(10);
    }
}
