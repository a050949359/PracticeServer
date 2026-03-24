<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

class AuthApiDoc
{
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
    public function login(): void {}

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Auth'],
        summary: 'Get current user profile',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Current user'),
        ]
    )]
    public function me(): void {}

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
    public function forgotPassword(): void {}

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
    public function resetPassword(): void {}
}
