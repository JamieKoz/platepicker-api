<?php
namespace App\Http\Controllers;

use App\Models\Cuisine;
use Illuminate\Http\JsonResponse;

class CuisineController extends Controller
{
    public function index(): JsonResponse
    {
        $cuisines = Cuisine::all();
        return response()->json($cuisines);
    }
}
