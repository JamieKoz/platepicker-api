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
                // Get the user meal directly
                $userMeal = UserMeal::find($tally->user_meal_id);

                if (!$userMeal) {
                    Log::warning('No matching user meal found', [
                        'tally_id' => $tally->id,
                        'user_id' => $tally->user_id,
                        'user_meal_id' => $tally->user_meal_id
                    ]);
                    return null;
                }

                // Load relationships before returning
                $userMeal->load(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);

                // Use UserMeal data
                return [
                    'id' => $tally->id,
                    'tally' => $tally->tally,
                    'last_selected_at' => $tally->last_selected_at,
                    'meal' => $userMeal
                ];
            })
            ->filter()
            ->values();
    }

    public function incrementMealTally(string $userId, int $userMealId): void
    {
        $user = User::where('auth_id', $userId)->firstOrFail();
        $userMeal = UserMeal::find($userMealId);

        if (!$userMeal) {
            Log::warning('User meal not found for tally increment', [
                'user_id' => $userId,
                'user_meal_id' => $userMealId
            ]);
            return;
        }

        $tally = UserMealTally::firstOrNew([
            'user_id' => $user->id,
            'user_meal_id' => $userMealId
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
        return UserMealTally::select('user_meal_id', DB::raw('SUM(tally) as total_tally'))
            ->groupBy('user_meal_id')
            ->orderBy('total_tally', 'desc')
            ->take(3)
            ->get()
            ->map(function ($tally) {
                $userMeal = UserMeal::find($tally->user_meal_id);

                if (!$userMeal) {
                    Log::warning('No user meal found for top meal tally', [
                        'user_meal_id' => $tally->user_meal_id,
                        'total_tally' => $tally->total_tally
                    ]);
                    return null;
                }

                // Load relationships
                $userMeal->load(['categories', 'cuisines', 'dietary', 'recipeLines.ingredient', 'recipeLines.measurement']);

                return [
                    'total_tally' => $tally->total_tally,
                    'meal' => $userMeal
                ];
            })
            ->filter()
            ->values();
    }
}
