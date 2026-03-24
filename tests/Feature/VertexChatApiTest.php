<?php

namespace Tests\Feature;

use App\Services\Google\Vertex\VertexChatService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VertexChatApiTest extends TestCase
{
    #[Test]
    public function test_vertex_chat_requires_prompt(): void
    {
        $response = $this->postJson('/api/google/vertex/chat', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt']);
    }

    #[Test]
    public function test_vertex_chat_returns_model_reply(): void
    {
        $this->mock(VertexChatService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ask')
                ->once()
                ->andReturn([
                    'reply' => '這是 Vertex AI 的回應',
                    'model' => 'gemini-2.0-flash-001',
                    'response_id' => 'response-123',
                    'usage' => [
                        'prompt_token_count' => 10,
                        'candidates_token_count' => 20,
                        'total_token_count' => 30,
                    ],
                ]);
        });

        $response = $this->postJson('/api/google/vertex/chat', [
            'prompt' => '你好',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Vertex AI reply generated',
                'code' => 'vertex_ai_reply_generated',
                'data' => [
                    'reply' => '這是 Vertex AI 的回應',
                    'model' => 'gemini-2.0-flash-001',
                ],
            ]);
    }

    #[Test]
    public function test_vertex_chat_returns_error_payload_when_service_fails(): void
    {
        $this->mock(VertexChatService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ask')
                ->once()
                ->andThrow(new RuntimeException('credentials missing'));
        });

        $response = $this->postJson('/api/google/vertex/chat', [
            'prompt' => '你好',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Vertex AI request failed',
                'code' => 'vertex_ai_request_failed',
                'error' => 'credentials missing',
            ]);
    }
}
