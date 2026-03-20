<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\InvitationRegisterRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\Auth\AuthService;
use App\Services\Auth\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

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

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Auth'],
        summary: 'Login with email and password',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'audience'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'audience', type: 'string', enum: ['public', 'admin']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Login success'),
        ]
    )]
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'audience' => 'required|string|in:public,admin',
        ]);

        return $this->authService->login($validated);
    }

    public function logout(Request $request): JsonResponse
    {
        return $this->authService->logout($request);
    }

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Auth'],
        summary: 'Get current user profile',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Current user'),
        ]
    )]
    public function me(Request $request): JsonResponse
    {
        return $this->authService->me($request);
    }

    public function updateMe(UpdateProfileRequest $request): JsonResponse
    {
        return $this->authService->updateMe($request->user(), $request->validated());
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return $this->authService->changePassword($request->user(), $request->validated());
    }

    #[OA\Post(
        path: '/api/auth/password/forgot',
        tags: ['Auth'],
        summary: 'Request password reset email',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Reset email request accepted'),
        ]
    )]
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        return $this->authService->forgotPassword($request->validated());
    }

    #[OA\Post(
        path: '/api/auth/password/reset',
        tags: ['Auth'],
        summary: 'Reset password using token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'expires', 'signature', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'expires', type: 'integer'),
                    new OA\Property(property: 'signature', type: 'string'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password reset success'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return $this->authService->resetPassword($request->validated());
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
