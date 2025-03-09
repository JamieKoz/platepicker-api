<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UserService;
use Illuminate\Support\Facades\Log;
use App\Services\TallyService;
use Illuminate\Http\JsonResponse;

class TallyController extends Controller
{
    protected $userService;
    protected $tallyService;

    public function __construct(UserService $userService, TallyService $tallyService)
    {
        $this->userService = $userService;
        $this->tallyService = $tallyService;
    }

    public function getFavourites(Request $request)
    {
        try {
            $authId = $request->header('X-User-ID');
            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 400);
            }

            $talliedRecipes = $this->tallyService->getFavourites($authId);
            return response()->json($talliedRecipes);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getTraceAsString()], 500);
            return response()->json(['error' => $e->getMessage()], 500);
            Log::error('Error getting favorites', [
                'error' => $e->getMessage(),
                'auth_id' => $authId
            ]);
            return response()->json(['error' => 'Failed to get favorites'], 500);
        }
    }

    public function getTopMeals()
    {
        try {
            $topRecipes = $this->tallyService->getTopMeals();
            return response()->json($topRecipes);
        } catch (\Exception $e) {
            Log::error('Error getting favorites', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to get favorites'], 500);
        }
    }

    public function incrementTally(Request $request, $id): JsonResponse
    {
        try {
            $authId = $request->header('X-User-ID');
            if (!$authId) {
                return response()->json(['error' => 'User ID required'], 200);
            }

            $this->tallyService->incrementMealTally($authId, $id);
            return response()->json(['message' => 'Tally incremented successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to increment tally'], 500);
        }
    }
}
