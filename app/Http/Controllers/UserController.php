<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'imageUrl' => 'nullable|string',
        ]);

        try {
            $user = User::updateOrCreate(
                ['auth_id' => $request->id],
                [
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make(Str::random(24)),
                    'is_active' => 1,
                    'is_admin' => $request->has('isAdmin') ? ($request->isAdmin ? 1 : 0) : 0,
                ]
            );

            return response()->json([
                'message' => 'User registered/updated successfully',
                'user' => $user,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error registering user',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
