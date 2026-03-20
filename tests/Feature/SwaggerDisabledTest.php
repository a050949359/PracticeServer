<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SwaggerDisabledTest extends TestCase
{
    #[Test]
    public function test_swagger_links_are_disabled(): void
    {
        $this->get('/api/documentation')->assertNotFound();
        $this->get('/docs')->assertNotFound();
        $this->get('/docs/asset/swagger-ui.css')->assertNotFound();
    }
}
