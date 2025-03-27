<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IngredientController extends Controller
{
    /**
     * Get all ingredients
     */
    public function index(Request $request): JsonResponse
    {
        $query = Ingredient::query();

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('abbreviation', 'like', "%{$searchTerm}%");
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        if (in_array($sortField, ['id', 'name', 'abbreviation', 'created_at', 'updated_at'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('name', 'asc'); // Default fallback
        }

        // Handle pagination
        $perPage = $request->get('per_page', 15);
        $ingredients = $query->paginate($perPage);

        return response()->json($ingredients);
    }

    /**
     * Search ingredients
     */
    public function search(Request $request): JsonResponse
    {
        $query = Ingredient::query();

        // Handle search term
        if ($request->has('q')) {
            $searchTerm = $request->get('q');
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        // Handle pagination
        $perPage = $request->get('per_page', 15);
        $ingredients = $query->paginate($perPage);

        return response()->json($ingredients);
    }

    /**
     * Create a new ingredient
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:ingredients',
                'category_id' => 'nullable|exists:categories,id',
                'default_measurement_id' => 'nullable|exists:measurements,id',
            ]);

            $ingredient = Ingredient::create($validated);

            return response()->json($ingredient, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create ingredient'], 500);
        }
    }

    /**
     * Get a specific ingredient
     */
    public function show(int $id): JsonResponse
    {
        $ingredient = Ingredient::find($id);

        if (!$ingredient) {
            return response()->json(['error' => 'Ingredient not found'], 404);
        }

        return response()->json($ingredient);
    }

    /**
     * Update an ingredient
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $ingredient = Ingredient::find($id);

            if (!$ingredient) {
                return response()->json(['error' => 'Ingredient not found'], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:ingredients,name,' . $id,
                'category_id' => 'nullable|exists:categories,id',
                'default_measurement_id' => 'nullable|exists:measurements,id',
            ]);

            $ingredient->update($validated);

            return response()->json($ingredient);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update ingredient'], 500);
        }
    }

    /**
     * Delete an ingredient
     */
    public function destroy(int $id): JsonResponse
    {
        $ingredient = Ingredient::find($id);

        if (!$ingredient) {
            return response()->json(['error' => 'Ingredient not found'], 404);
        }

        try {
            $ingredient->delete();
            return response()->json(['message' => 'Ingredient deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete ingredient'], 500);
        }
    }
}
