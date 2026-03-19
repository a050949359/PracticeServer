<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvitationRegisterRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\Auth\AuthService;
use App\Services\Auth\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
        private InvitationService $invitationService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->authService->register(
            $request->validated()
        );
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        return $this->authService->login($validated);
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->authService->logout($request);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->authService->me($request);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        return $this->authService->resendVerificationEmail($request);
    }

    public function resendVerificationEmailToUser(int $userId): JsonResponse
    {
        return $this->authService->resendVerificationEmailToUser($userId);
    }

    public function showInvitation(string $token): JsonResponse
    {
        return $this->invitationService->showByToken($token);
    }

    public function registerByInvitation(InvitationRegisterRequest $request): JsonResponse
    {
        return $this->invitationService->completeRegistration($request->validated());
    }

    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        $verificationResult = $this->authService->verifyEmail($request, $id, $hash);

        return redirect()->to('/register/verify-email?'.http_build_query([
            'status' => $verificationResult['status'],
            'code' => $verificationResult['code'],
            'verified_at' => $verificationResult['email_verified_at'],
            'redirect_to' => $verificationResult['redirect_to'],
        ]));
    }
}
