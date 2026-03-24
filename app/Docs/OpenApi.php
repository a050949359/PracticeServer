<?php

namespace App\Docs;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'PracticeServer API',
    description: 'Auth, invitation, and user APIs for PracticeServer'
)]
#[OA\Server(
    url: 'http://localhost',
    description: 'Application server'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'apiKey',
    in: 'header',
    name: 'Authorization',
    description: 'Bearer token auth, format: Bearer {token}'
)]
class OpenApi {}
