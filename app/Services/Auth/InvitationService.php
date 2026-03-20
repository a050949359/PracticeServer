<?php

namespace App\Services\Auth;

use App\Events\SendEmailRequested;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private DatabaseManager $database,
        private RegistrationAssignmentService $registrationAssignmentService,
    ) {}

    public function create(array $validated, User $inviter): JsonResponse
    {
        $expiresInHours = $validated['expires_in_hours'] ?? 72;
        $token = Str::random(64);

        $invitation = $this->database->transaction(function () use ($validated, $inviter, $token, $expiresInHours) {
            return Invitation::query()->create([
                'email' => $validated['email'],
                'name' => $validated['name'] ?? null,
                'context' => $validated['context'],
                'token' => $token,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addHours($expiresInHours),
            ]);
        }
        );

        event(new SendEmailRequested(
            email: $invitation->email,
            type: 'registration_invite',
            name: $invitation->name,
            data: [
                'action_url' => sprintf(
                    '%s/register?token=%s',
                    rtrim(config('app.url'), '/'),
                    $invitation->token,
                ),
            ],
        ));

        return response()->json([
            'message' => 'Invitation created',
            'code' => 'invitation_created',
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'name' => $invitation->name,
                'context' => $invitation->context,
                'expires_at' => $invitation->expires_at,
            ],
        ], 201);
    }

    public function showByToken(string $token): JsonResponse
    {
        $invitation = Invitation::query()->where('token', $token)->first();

        if (! $invitation) {
            return response()->json([
                'message' => 'Invitation not found',
                'code' => 'invitation_not_found',
            ], 404);
        }

        $stateErrorResponse = $this->validateInvitationState($invitation);
        if ($stateErrorResponse) {
            return $stateErrorResponse;
        }

        return response()->json([
            'message' => 'Invitation found',
            'code' => 'invitation_found',
            'invitation' => [
                'email' => $invitation->email,
                'name' => $invitation->name,
                'context' => $invitation->context,
                'expires_at' => $invitation->expires_at,
            ],
        ]);
    }

    public function completeRegistration(array $validated): JsonResponse
    {
        $invitation = Invitation::query()->where('token', $validated['token'])->first();

        if (! $invitation) {
            return response()->json([
                'message' => 'Invitation not found',
                'code' => 'invitation_not_found',
            ], 404);
        }

        $stateErrorResponse = $this->validateInvitationState($invitation);
        if ($stateErrorResponse) {
            return $stateErrorResponse;
        }

        if (User::query()->where('email', $invitation->email)->exists()) {
            return response()->json([
                'message' => 'This invitation email has already been registered',
                'code' => 'invitation_email_already_registered',
            ], 422);
        }

        [$registeredUser, $roleName] = $this->database->transaction(function () use ($validated, $invitation) {
            $resolvedName = $invitation->name ?: Str::before($invitation->email, '@');

            [$user, $team, $role] = $this->registrationAssignmentService->createUserWithAssignment([
                'name' => $resolvedName,
                'email' => $invitation->email,
                'password' => $validated['password'],
            ], $invitation->context);

            $invitation->accepted_at = now();
            $invitation->save();

            return [$user, $role->name];
        });

        $token = $registeredUser->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Invitation registration completed',
            'code' => 'invitation_registration_completed',
            'token' => $token,
            'role' => $roleName,
            'redirect_to' => $roleName === 'staff' ? '/admin' : '/',
            'user' => [
                'id' => $registeredUser->id,
                'name' => $registeredUser->name,
                'email' => $registeredUser->email,
            ],
        ], 201);
    }

    private function validateInvitationState(Invitation $invitation): ?JsonResponse
    {
        if ($invitation->accepted_at !== null) {
            return response()->json([
                'message' => 'Invitation already used',
                'code' => 'invitation_already_used',
            ], 409);
        }

        if ($invitation->expires_at?->isPast()) {
            return response()->json([
                'message' => 'Invitation expired',
                'code' => 'invitation_expired',
            ], 422);
        }

        return null;
    }
}
