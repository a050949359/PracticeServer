<?php

namespace Tests\Feature;

use App\Services\Google\Vertex\VertexImageService;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VertexImageApiTest extends TestCase
{
    #[Test]
    public function test_vertex_image_requires_prompt_and_image(): void
    {
        $response = $this->post('/api/google/vertex/image', [], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['prompt', 'image']);
    }

    #[Test]
    public function test_vertex_image_returns_model_reply(): void
    {
        $this->mock(VertexImageService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ask')
                ->once()
                ->andReturn([
                    'reply' => '這是 Vertex AI 影像分析的回應',
                    'model' => 'gemini-2.0-flash-001',
                    'response_id' => 'response-image-123',
                    'usage' => [
                        'prompt_token_count' => 10,
                        'candidates_token_count' => 20,
                        'total_token_count' => 30,
                    ],
                ]);
        });

        $response = $this->post('/api/google/vertex/image', [
            'prompt' => '請描述圖片內容',
            'image' => UploadedFile::fake()->create('sample.jpg', 100, 'image/jpeg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Vertex AI image reply generated',
                'code' => 'vertex_ai_image_reply_generated',
                'data' => [
                    'reply' => '這是 Vertex AI 影像分析的回應',
                    'model' => 'gemini-2.0-flash-001',
                ],
            ]);
    }

    #[Test]
    public function test_vertex_image_returns_error_payload_when_service_fails(): void
    {
        $this->mock(VertexImageService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('ask')
                ->once()
                ->andThrow(new RuntimeException('credentials missing'));
        });

        $response = $this->post('/api/google/vertex/image', [
            'prompt' => '請描述圖片內容',
            'image' => UploadedFile::fake()->create('sample.jpg', 100, 'image/jpeg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Vertex AI image request failed',
                'code' => 'vertex_ai_image_request_failed',
                'error' => 'credentials missing',
            ]);
    }
}
