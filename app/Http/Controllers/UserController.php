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

        // Check if user already exists by auth_id (Clerk ID)
        $user = User::where('auth_id', $request->id)->first();

        if ($user) {
            // Update existing user
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                // Don't need to update imageUrl as it's not in your schema
            ]);
        } else {
            // Create new user
            $user = User::create([
                'auth_id' => $request->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make(Str::random(24)),
                'is_active' => 1,
                'is_admin' => 0
            ]);
            if ($request->has('isAdmin')) {
                $user->is_admin = $request->isAdmin ? 1 : 0;
                $user->save();
            }
        }

        return response()->json([
            'message' => 'User registered/updated successfully',
            'user' => $user,
        ], 201);
    }
}
