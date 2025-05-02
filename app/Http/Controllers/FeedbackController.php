<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\FeedbackMail;
use Illuminate\Support\Facades\Log;

class FeedbackController extends Controller
{
    public function submit(Request $request)
    {
        // Validate the incoming request
        $validated = $request->validate([
            'type' => 'required|string|in:suggestion,bug,compliment,other',
            'message' => 'required|string|max:2000',
            'email' => 'nullable|email',
            'rating' => 'required|integer|min:0|max:5',
        ]);

        try {
            // Get user data from headers if available
            $userId = $request->header('X-User-ID');
            $userData = $request->header('X-User-Data');

            // Add user info to the feedback data
            $feedbackData = array_merge($validated, [
                'user_id' => $userId,
                'user_data' => $userData,
                'submitted_at' => now(),
            ]);

            // Send email
            Mail::to('feedback@platepicker.net')->send(new FeedbackMail($feedbackData));

            // Optionally log feedback for analytics
            Log::info('Feedback submitted', $feedbackData);

            // Return success response
            return response()->json([
                'status' => 'success',
                'message' => 'Feedback submitted successfully'
            ], 200);
        } catch (\Throwable $e) {
            // Log error
            Log::error('Failed to process feedback', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            // Return error response
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
