<?php

namespace App\Http\Controllers;

use App\Models\Measurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MeasurementController extends Controller
{
    /**
     * Get all measurements with sorting and searching
     */
    public function index(Request $request): JsonResponse
    {
        $query = Measurement::query();

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('abbreviation', 'like', "%{$searchTerm}%");
        }

        // Handle sorting
        $sortField = $request->get('sort_field', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        // Make sure the sort field is valid
        if (in_array($sortField, ['id', 'name', 'abbreviation', 'created_at', 'updated_at'])) {
            $query->orderBy($sortField, $sortDirection);
        } else {
            $query->orderBy('name', 'asc'); // Default fallback
        }

        // Handle pagination
        $perPage = $request->get('per_page', 15);
        $measurements = $query->paginate($perPage);

        return response()->json($measurements);
    }

    /**
     * Create a new measurement
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:measurements',
                'abbreviation' => 'nullable|string|max:10',
            ]);

            $measurement = Measurement::create($validated);

            return response()->json($measurement, 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create measurement'], 500);
        }
    }

    /**
     * Get a specific measurement
     */
    public function show(int $id): JsonResponse
    {
        $measurement = Measurement::find($id);

        if (!$measurement) {
            return response()->json(['error' => 'Measurement not found'], 404);
        }

        return response()->json($measurement);
    }

    /**
     * Update a measurement
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $measurement = Measurement::find($id);

            if (!$measurement) {
                return response()->json(['error' => 'Measurement not found'], 404);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:measurements,name,' . $id,
                'abbreviation' => 'nullable|string|max:10',
            ]);

            $measurement->update($validated);

            return response()->json($measurement);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update measurement'], 500);
        }
    }

    /**
     * Delete a measurement
     */
    public function destroy(int $id): JsonResponse
    {
        $measurement = Measurement::find($id);

        if (!$measurement) {
            return response()->json(['error' => 'Measurement not found'], 404);
        }

        try {
            $measurement->delete();
            return response()->json(['message' => 'Measurement deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete measurement'], 500);
        }
    }
}
