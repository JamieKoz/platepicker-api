<?php

namespace App\Http\Controllers;

use App\Models\Dietary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DietaryController extends Controller
{
    /**
     * Get all dietary requirements with sorting and searching
     */
    public function index(Request $request): JsonResponse
    {
        $query = Dietary::query();

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
        $dietaries = $query->paginate($perPage);

        return response()->json($dietaries);
    }

    /**
     * Create a new dietary requirement
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:dietaries',
            ]);

            $dietary = Dietary::create($validated);

            return response()->json($dietary, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create dietary requirement'], 500);
        }
    }

    /**
     * Get a specific dietary requirement
     */
    public function show(int $id): JsonResponse
    {
        $dietary = Dietary::find($id);

        if (!$dietary) {
            return response()->json(['error' => 'Dietary requirement not found'], 404);
        }

        return response()->json($dietary);
    }

    /**
     * Update a dietary requirement
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $dietary = Dietary::find($id);

            if (!$dietary) {
                return response()->json(['error' => 'Dietary requirement not found'], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:dietaries,name,' . $id,
            ]);

            $dietary->update($validated);

            return response()->json($dietary);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update dietary requirement'], 500);
        }
    }

    /**
     * Delete a dietary requirement
     */
    public function destroy(int $id): JsonResponse
    {
        $dietary = Dietary::find($id);

        if (!$dietary) {
            return response()->json(['error' => 'Dietary requirement not found'], 404);
        }

        try {
            $dietary->delete();
            return response()->json(['message' => 'Dietary requirement deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete dietary requirement'], 500);
        }
    }
}
