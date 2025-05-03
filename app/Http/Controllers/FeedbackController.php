<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    public function submit(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:suggestion,bug,compliment,other',
            'message' => 'required|string|max:2000',
            'email' => 'nullable|email',
            'rating' => 'required|integer|min:0|max:5',
        ]);

        try {
            $userData = json_decode($request->header('X-User-Data'), true);
            if (!$userData || !isset($userData['id'])) {
                return response()->json(['error' => 'User data not provided.'], 401);
            }

            $userId = $userData['id'];
            $email = $validated['email'] ?? $userData['email'] ?? null;

            $feedbackId = DB::table('feedback')->insertGetId([
                'type' => $validated['type'],
                'message' => $validated['message'],
                'email' => $email,
                'rating' => $validated['rating'],
                'user_id' => $userId,
                'user_data' => json_encode($userData),
                'is_resolved' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Feedback submitted', ['feedback_id' => $feedbackId]);

            return response()->json([
                'status' => 'success',
                'message' => 'Feedback submitted successfully',
                'feedback_id' => $feedbackId
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Failed to process feedback', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit feedback: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function index()
    {
        $feedback = DB::table('feedback')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($feedback);
    }

    public function show($id)
    {
        $feedback = DB::table('feedback')->find($id);

        if (!$feedback) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feedback not found'
            ], 404);
        }

        return response()->json($feedback);
    }

    public function update(Request $request, $id)
    {
        // Validate the request
        $validated = $request->validate([
            'is_resolved' => 'sometimes|boolean',
            'resolution_notes' => 'sometimes|string|nullable',
        ]);

        // Update the feedback record
        DB::table('feedback')
            ->where('id', $id)
            ->update(array_merge($validated, ['updated_at' => now()]));

        // Get the updated feedback
        $feedback = DB::table('feedback')->find($id);

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback updated successfully',
            'feedback' => $feedback
        ]);
    }
}
