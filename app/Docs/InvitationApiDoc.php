<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

class InvitationApiDoc
{
    #[OA\Post(
        path: '/api/admin/v1/invitations',
        tags: ['Invitation'],
        summary: 'Create registration invitation',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'context'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'name', type: 'string', nullable: true),
                    new OA\Property(property: 'context', type: 'string', enum: ['user_invited_register', 'staff_invited_register']),
                    new OA\Property(property: 'expires_in_hours', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Invitation created'),
        ]
    )]
    public function store(): void {}
}
