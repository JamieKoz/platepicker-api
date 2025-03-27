<?php

namespace App\Http\Controllers;

use App\Models\Cuisine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CuisineController extends Controller
{
    /**
     * Get all cuisines with sorting and searching
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cuisine::query();

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        // Make sure the sort field is valid
        if (in_array($sortField, ['id', 'name', 'created_at', 'updated_at'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('name', 'asc'); // Default fallback
        }

        // Handle pagination
        $perPage = $request->get('per_page', 15);
        $cuisines = $query->paginate($perPage);

        return response()->json($cuisines);
    }

    /**
     * Create a new cuisine
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:cuisines',
            ]);

            $cuisine = Cuisine::create($validated);

            return response()->json($cuisine, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create cuisine'], 500);
        }
    }

    /**
     * Get a specific cuisine
     */
    public function show(int $id): JsonResponse
    {
        $cuisine = Cuisine::find($id);

        if (!$cuisine) {
            return response()->json(['error' => 'Cuisine not found'], 404);
        }

        return response()->json($cuisine);
    }

    /**
     * Update a cuisine
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $cuisine = Cuisine::find($id);

            if (!$cuisine) {
                return response()->json(['error' => 'Cuisine not found'], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:cuisines,name,' . $id,
            ]);

            $cuisine->update($validated);

            return response()->json($cuisine);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update cuisine'], 500);
        }
    }

    /**
     * Delete a cuisine
     */
    public function destroy(int $id): JsonResponse
    {
        $cuisine = Cuisine::find($id);

        if (!$cuisine) {
            return response()->json(['error' => 'Cuisine not found'], 404);
        }

        try {
            $cuisine->delete();
            return response()->json(['message' => 'Cuisine deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete cuisine'], 500);
        }
    }
}
