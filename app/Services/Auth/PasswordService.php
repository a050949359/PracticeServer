<?php

namespace App\Services\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Handles password reset and password change workflows.
 */
class PasswordService
{
    public function __construct(
        private DatabaseManager $database,
        private EmailService $emailService,
    ) {}

    /**
     * @param  array{current_password:string,password:string,password_confirmation?:string}  $validated
     */
    public function changePassword(?User $user, array $validated): JsonResponse
    {
        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect',
                'code' => 'current_password_incorrect',
            ], 422);
        }

        return $this->applyPasswordChangeWithPolicies($user, $validated['password']);
    }

    /**
     * @param  array{email:string}  $validated
     */
    public function forgotPassword(array $validated): JsonResponse
    {
        $user = User::query()->where('email', $validated['email'])->first();

        if ($user) {
            $token = Password::createToken($user);
            $this->emailService->sendPasswordResetTo($user, $token);
        }

        return response()->json([
            'message' => __(Password::RESET_LINK_SENT),
        ]);
    }

    /**
     * @param  array{email:string,token:string,password:string,password_confirmation?:string,expires:string|int,signature:string}  $validated
     */
    public function resetPassword(array $validated): JsonResponse
    {
        if (! $this->hasValidResetSignature($validated)) {
            return response()->json([
                'message' => 'Invalid or expired reset signature',
                'code' => 'invalid_reset_signature',
            ], 403);
        }

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
                'code' => 'user_not_found',
            ], 404);
        }

        $policyViolation = $this->checkPasswordPolicyViolation($user, $validated['password']);
        if ($policyViolation) {
            return response()->json($policyViolation['payload'], $policyViolation['status']);
        }

        $status = Password::broker()->reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $this->recordPreviousPassword($user);

                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $this->prunePasswordHistory($user);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
                'code' => 'reset_failed',
            ], 422);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }

    /**
     * @param  array{email:string,token:string,expires:string|int,signature:string}  $payload
     */
    private function hasValidResetSignature(array $payload): bool
    {
        $expires = (int) $payload['expires'];

        if ($expires <= 0 || now()->timestamp > $expires) {
            return false;
        }

        $expectedSignature = hash_hmac(
            'sha256',
            $this->buildResetSignaturePayload($payload['email'], $payload['token'], $expires),
            (string) config('app.key'),
        );

        return hash_equals($expectedSignature, (string) $payload['signature']);
    }

    private function buildResetSignaturePayload(string $email, string $token, int $expires): string
    {
        return implode('|', [$email, $token, $expires]);
    }

    /**
     * Persist a password change after all policy checks pass.
     */
    private function applyPasswordChangeWithPolicies(User $user, string $nextPassword): JsonResponse
    {
        $policyViolation = $this->checkPasswordPolicyViolation($user, $nextPassword);
        if ($policyViolation) {
            return response()->json($policyViolation['payload'], $policyViolation['status']);
        }

        $this->database->transaction(function () use ($user, $nextPassword): void {
            $this->recordPreviousPassword($user);

            $user->forceFill([
                'password' => $nextPassword,
            ])->save();

            $this->prunePasswordHistory($user);

            $currentToken = $user->currentAccessToken();
            if ($currentToken) {
                $user->tokens()->where('id', '!=', $currentToken->id)->delete();
            } else {
                $user->tokens()->delete();
            }
        });

        return response()->json([
            'message' => 'Password updated',
        ]);
    }

    /**
     * @return array{payload: array{message: string, code: string}, status: int}|null
     */
    private function checkPasswordPolicyViolation(User $user, string $nextPassword): ?array
    {
        if (Hash::check($nextPassword, $user->password)) {
            return [
                'payload' => [
                    'message' => 'New password must be different from current password',
                    'code' => 'password_reused',
                ],
                'status' => 422,
            ];
        }

        $cooldownMinutes = (int) config('auth.password_policy.change_cooldown_minutes', 10);
        $latestChangedAt = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->latest('changed_at')
            ->value('changed_at');

        if ($latestChangedAt !== null) {
            $earliestNextChangeAt = Carbon::parse($latestChangedAt)->addMinutes($cooldownMinutes);
            if (now()->lt($earliestNextChangeAt)) {
                return [
                    'payload' => [
                        'message' => 'Password can only be changed once every '.$cooldownMinutes.' minutes',
                        'code' => 'password_change_cooldown',
                    ],
                    'status' => 429,
                ];
            }
        }

        $historyGenerations = (int) config('auth.password_policy.history_generations', 3);
        $recentPasswordHashes = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->latest('changed_at')
            ->limit($historyGenerations)
            ->pluck('password_hash');

        foreach ($recentPasswordHashes as $oldHash) {
            if (Hash::check($nextPassword, $oldHash)) {
                return [
                    'payload' => [
                        'message' => 'Cannot reuse the last '.$historyGenerations.' passwords',
                        'code' => 'password_history_violation',
                    ],
                    'status' => 422,
                ];
            }
        }

        return null;
    }

    /**
     * Store current password hash as one generation in history.
     */
    private function recordPreviousPassword(User $user): void
    {
        PasswordHistory::query()->create([
            'user_id' => $user->id,
            'password_hash' => $user->password,
            'changed_at' => now(),
        ]);
    }

    /**
     * Keep only the latest configured password history generations.
     */
    private function prunePasswordHistory(User $user): void
    {
        $historyGenerations = (int) config('auth.password_policy.history_generations', 3);

        $idsToKeep = PasswordHistory::query()
            ->where('user_id', $user->id)
            ->latest('changed_at')
            ->limit($historyGenerations)
            ->pluck('id');

        PasswordHistory::query()
            ->where('user_id', $user->id)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
