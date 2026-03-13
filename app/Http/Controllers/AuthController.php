<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create($validated);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 422);
        }

        $user = Auth::user();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }
        
        $tokenId = $user->currentAccessToken()->id;
        
        $user->tokens()->where('id', $tokenId)->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}
