<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    public function register(array $validated): void
    {
        User::create($validated);
    }

    public function login(array $validated): JsonResponse
    {
        if (! Auth::attempt($validated)) {
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

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
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

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
