<?php
namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IngredientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['hello'=>200]);
        /* $ingredients = Ingredient::where('', $request->get()); */
        /* return response()->json($ingredients); */
    }
    public function search(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q', '');
            $limit = $request->get('limit', 10);

            $ingredients = Ingredient::where('name', 'LIKE', "%{$query}%")
                ->orderBy('name')
                ->limit($limit)
                ->get();

            return response()->json($ingredients);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'Failed to search ingredients'], 500);
        }
    }
}
