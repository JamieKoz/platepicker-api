<?php

namespace App\Http\Controllers;

use App\Models\Dietary;
use Illuminate\Http\JsonResponse;

class DietaryController extends Controller
{
    public function index(): JsonResponse
    {
        $dietaryRequirements = Dietary::all();
        return response()->json($dietaryRequirements);
    }
}
