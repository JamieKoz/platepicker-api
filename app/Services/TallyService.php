<?php
namespace App\Services;
use App\Models\User;
use App\Models\UserMeal;
use App\Models\Recipe;
use App\Models\UserMealTally;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TallyService
{
    public function getFavourites(string $authId)
    {
        $user = User::where('auth_id', $authId)->firstOrFail();

        return UserMealTally::where('user_id', $user->id)
            ->orderBy('tally', 'desc')
            ->take(3)
            ->get()
            ->map(function ($tally) {
                // First try to get from UserMeal
                $userMeal = UserMeal::where('recipe_id', $tally->recipe_id)
                    ->where('user_id', $tally->user_id)
                    ->first();

                // If not in UserMeal, try to get from Recipe
                if (!$userMeal || (!$userMeal->title && $userMeal->recipe_id)) {
                    $recipe = Recipe::find($tally->recipe_id);
                    if (!$recipe) {
                        Log::warning('No matching meal or recipe found', [
                            'tally_id' => $tally->id,
                            'user_id' => $tally->user_id,
                            'recipe_id' => $tally->recipe_id
                        ]);
                        return null;
                    }

                    // Use Recipe data
                    return [
                        'id' => $tally->id,
                        'tally' => $tally->tally,
                        'last_selected_at' => $tally->last_selected_at,
                        'meal' => [
                            'id' => $recipe->id,
                            'title' => $recipe->title,
                            'ingredients' => $recipe->ingredients,
                            'instructions' => $recipe->instructions,
                            'image_name' => $recipe->image_name,
                            'recipe_id' => $recipe->id,
                            'cleaned_ingredients' => $recipe->cleaned_ingredients,
                            'active' => true,
                            'created_at' => $recipe->created_at,
                            'updated_at' => $recipe->updated_at
                        ]
                    ];
                }

                // Use UserMeal data
                return [
                    'id' => $tally->id,
                    'tally' => $tally->tally,
                    'last_selected_at' => $tally->last_selected_at,
                    'meal' => [
                        'id' => $userMeal->id,
                        'title' => $userMeal->title,
                        'ingredients' => $userMeal->ingredients,
                        'instructions' => $userMeal->instructions,
                        'image_name' => $userMeal->image_name,
                        'recipe_id' => $userMeal->recipe_id,
                        'cleaned_ingredients' => $userMeal->cleaned_ingredients,
                        'active' => $userMeal->active,
                        'created_at' => $userMeal->created_at,
                        'updated_at' => $userMeal->updated_at
                    ]
                ];
            })
            ->filter()
            ->values();
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

    public function getTopMeals()
    {
        return UserMealTally::select('recipe_id', DB::raw('SUM(tally) as total_tally'))
            ->groupBy('recipe_id')
            ->orderBy('total_tally', 'desc')
            ->take(3)
            ->get()
            ->map(function ($tally) {
                $recipe = Recipe::find($tally->recipe_id);

                if (!$recipe) {
                    Log::warning('No recipe found for top meal tally', [
                        'recipe_id' => $tally->recipe_id,
                        'total_tally' => $tally->total_tally
                    ]);
                    return null;
                }

                return [
                    'total_tally' => $tally->total_tally,
                    'meal' => [
                        'id' => $recipe->id,
                        'title' => $recipe->title,
                        'ingredients' => $recipe->ingredients,
                        'instructions' => $recipe->instructions,
                        'image_name' => $recipe->image_name,
                        'recipe_id' => $recipe->id,
                        'cleaned_ingredients' => $recipe->cleaned_ingredients,
                        'active' => true,
                        'created_at' => $recipe->created_at,
                        'updated_at' => $recipe->updated_at
                    ]
                ];
            })
            ->filter()
            ->values();
    }
}
