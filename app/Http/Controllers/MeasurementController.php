<?php
namespace App\Http\Controllers;

use App\Models\Measurement;
use Illuminate\Http\JsonResponse;

class MeasurementController extends Controller
{
    public function index(): JsonResponse
    {
        $measurements = Measurement::all();
        return response()->json($measurements);
    }
}
