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
            ]);
        } else {
            // Create new user
            $user = new User();
            $user->auth_id = $request->id;
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make(Str::random(24));
            $user->is_active = 1;
            $user->is_admin = $request->has('isAdmin') ? ($request->isAdmin ? 1 : 0) : 0;
            $user->save();
        }

        return response()->json([
            'message' => 'User registered/updated successfully',
            'user' => $user,
        ], 201);
    }
}
