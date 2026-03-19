<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthService
{
    private const DEFAULT_REGISTER_CONTEXT = 'user_self_register';

    public function __construct(
        private DatabaseManager $database,
        private RegistrationAssignmentService $registrationAssignmentService,
        private VerificationEmailService $verificationEmailService,
    ) {}

    public function register(array $validated): JsonResponse
    {
        $context = $validated['context'] ?? self::DEFAULT_REGISTER_CONTEXT;

        $registrationResult = $this->database->transaction(function () use ($validated, $context) {
            return $this->registrationAssignmentService->createUserWithAssignment([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ], $context);
        });

        [$registeredUser, $team, $role] = $registrationResult;

        return response()->json([
            'message' => 'Registered successfully',
            'user' => [
                'id' => $registeredUser->id,
                'name' => $registeredUser->name,
                'email' => $registeredUser->email,
            ],
            'context' => $context,
            'team' => $team->name,
            'role' => $role->name,
        ], 201);
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
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
            'is_staff' => $user->roles()->where('name', 'staff')->exists(),
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        return $this->sendVerificationEmailToUserModel($user);
    }

    public function resendVerificationEmailToUser(int $userId): JsonResponse
    {
        $user = User::query()->find($userId);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        return $this->sendVerificationEmailToUserModel($user);
    }

    /**
     * @return array{status:string,code:string,email_verified_at:?string,redirect_to:string}
     */
    public function verifyEmail(Request $request, int $id, string $hash): array
    {
        if (! $request->hasValidSignature()) {
            return [
                'status' => 'error',
                'code' => 'invalid_signature',
                'email_verified_at' => null,
                'redirect_to' => '/',
            ];
        }

        $user = User::query()->find($id);

        if (! $user) {
            return [
                'status' => 'error',
                'code' => 'user_not_found',
                'email_verified_at' => null,
                'redirect_to' => '/',
            ];
        }

        $redirectTo = $user->roles()->where('name', 'staff')->exists() ? '/admin' : '/';

        if (! hash_equals((string) $hash, sha1($user->email))) {
            return [
                'status' => 'error',
                'code' => 'invalid_hash',
                'email_verified_at' => null,
                'redirect_to' => $redirectTo,
            ];
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'status' => 'info',
                'code' => 'already_verified',
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'redirect_to' => $redirectTo,
            ];
        }

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        return [
            'status' => 'success',
            'code' => 'verified',
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'redirect_to' => $redirectTo,
        ];
    }

    private function sendVerificationEmailToUserModel(User $user): JsonResponse
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
                'email_verified_at' => $user->email_verified_at,
            ], 409);
        }

        $this->verificationEmailService->sendTo($user);

        return response()->json([
            'message' => 'Verification email sent',
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
            ],
        ]);
    }
}
