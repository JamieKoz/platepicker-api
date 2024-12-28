<?php

namespace App\Services;

use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;

class RecipeService
{
    public function getRandomRecipes($count = 1)
    {
        $recipes = Recipe::inRandomOrder()->take($count)->get();
        foreach ($recipes as $recipe) {
            $imagePath = 'food-images/food-images/public/' . $recipe->image_name;
            if (Storage::exists($imagePath)) {
                $recipe->image_content = Storage::get($imagePath);
            } else {
                $recipe->image_content = null; // Handle the case where the image does not exist
            }
        }
        return $recipes;
    }

    public function getRecipeByName(string $name)
    {
        $recipe = Recipe::query()->where('title', $name)->get();

        return $recipe;
    }

    public function getRandomRecipesInactive($count = 27)
    {
        $recipes = Recipe::inRandomOrder()->where('active', 0)->take($count)->get();
        foreach ($recipes as $recipe) {
            $imagePath = 'food-images/food-images/public/' . $recipe->image_name;
            $recipe->image_content = null; // Handle the case where the image does not exist
            if (Storage::exists($imagePath)) {
                $recipe->image_content = Storage::get($imagePath);
            }
        }
        return $recipes;
    }

    public function getRecipeByNameInactive(string $name)
    {
        $recipe = Recipe::query()->where('title', $name)->where('active', 0)->get();

        return $recipe;
    }

    public function getRecipesByJamiePreference(){
        $recipes = Recipe::inRandomOrder()->where('active', 1)->where('title', 'Wiener Schnitzel')->take(25)->get();
        return $recipes;
    }

    public function getRecipeList()
    {
        return Recipe::orderBy('title', 'asc')->paginate(25);
    }

    public function toggleStatus($mealId)
    {
        $recipe = Recipe::findOrFail($mealId);
        $recipe->active = !$recipe->active;
        $recipe->save();
        return $recipe;
    }

    public function search($searchTerm){

        return Recipe::orderBy('title', 'ASC')->where('title', 'LIKE', '%' . $searchTerm . '%')->get();
    }

}
