<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PracticeApiTest extends TestCase
{
    #[Test]
    public function test_api_preflight_request_returns_cors_headers_for_allowed_origin(): void
    {
        $response = $this->options('/api/practice/echo', [], [
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
        ]);

        $response
            ->assertStatus(204)
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    #[Test]
    public function test_echo_api_successfully_returns_message_and_length(): void
    {
        $response = $this->postJson('/api/practice/echo', [
            'text' => 'hello world',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'hello world',
                'length' => 11,
            ]);
    }

    #[Test]
    public function test_echo_api_uses_echo_text_rules(): void
    {
        $response = $this->postJson('/api/practice/echo', [
            'a' => 5,
            'b' => 6,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text'])
            ->assertJsonMissingValidationErrors(['a', 'b']);
    }

    #[Test]
    public function test_sum_api_successfully_returns_sum(): void
    {
        $response = $this->postJson('/api/practice/sum', [
            'a' => 10,
            'b' => 4.5,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'a' => 10,
                'b' => 4.5,
                'sum' => 14.5,
            ]);
    }

    #[Test]
    public function test_sum_api_uses_sum_values_rules(): void
    {
        $response = $this->postJson('/api/practice/sum', [
            'text' => 'this should not be required here',
            'a' => 'abc',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['a', 'b'])
            ->assertJsonMissingValidationErrors(['text']);
    }
}
