<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SwaggerRoutesDisabledByConfigTest extends TestCase
{
    #[Test]
    public function test_swagger_routes_are_not_registered_when_routes_are_null(): void
    {
        $this->get('/api/documentation')->assertNotFound();
        $this->get('/docs')->assertNotFound();
        $this->get('/api/oauth2-callback')->assertNotFound();
    }
}
