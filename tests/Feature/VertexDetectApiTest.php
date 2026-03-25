<?php

namespace Tests\Feature;

use App\Models\VertexOcrResult;
use App\Services\Google\Vertex\VertexDetectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class VertexDetectApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_vertex_detect_requires_image(): void
    {
        $response = $this->post('/api/google/vertex/image/detect', [], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    #[Test]
    public function test_vertex_detect_returns_detection_result(): void
    {
        $this->mock(VertexDetectService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('detect')
                ->once()
                ->andReturn([
                    'text' => 'INVOICE #123',
                    'page_count' => 1,
                    'text_block_count' => 2,
                    'provider' => 'cloud_vision_ocr',
                    'types' => ['DOCUMENT_TEXT_DETECTION'],
                    'raw_response' => [],
                    'record' => [
                        'id' => 99,
                        'image_name' => 'sample.jpg',
                        'image_path' => 'vertex-ocr-images/sample.jpg',
                        'image_url' => '/storage/vertex-ocr-images/sample.jpg',
                        'created_at' => '2026-03-25T12:00:00+00:00',
                    ],
                ]);
        });

        $response = $this->post('/api/google/vertex/image/detect', [
            'image' => UploadedFile::fake()->create('sample.jpg', 100, 'image/jpeg'),
            'types' => ['DOCUMENT_TEXT_DETECTION'],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Cloud Vision OCR generated',
                'code' => 'cloud_vision_ocr_generated',
                'data' => [
                    'text' => 'INVOICE #123',
                    'provider' => 'cloud_vision_ocr',
                    'types' => ['DOCUMENT_TEXT_DETECTION'],
                    'record' => [
                        'image_name' => 'sample.jpg',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_vertex_detect_returns_error_payload_when_service_fails(): void
    {
        $this->mock(VertexDetectService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('detect')
                ->once()
                ->andThrow(new RuntimeException('credentials missing'));
        });

        $response = $this->post('/api/google/vertex/image/detect', [
            'image' => UploadedFile::fake()->create('sample.jpg', 100, 'image/jpeg'),
            'types' => ['TEXT_DETECTION'],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Cloud Vision OCR request failed',
                'code' => 'cloud_vision_ocr_request_failed',
                'error' => 'credentials missing',
            ]);
    }

    #[Test]
    public function test_vertex_detect_rejects_invalid_type(): void
    {
        $response = $this->post('/api/google/vertex/image/detect', [
            'image' => UploadedFile::fake()->create('sample.jpg', 100, 'image/jpeg'),
            'types' => ['INVALID_FEATURE_TYPE'],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['types.0']);
    }

    #[Test]
    public function test_vertex_detect_returns_object_coordinates_for_object_localization(): void
    {
        $this->mock(VertexDetectService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('detect')
                ->once()
                ->andReturn([
                    'text' => null,
                    'page_count' => null,
                    'text_block_count' => null,
                    'objects' => [
                        [
                            'name' => 'Laptop',
                            'score' => 0.98,
                            'bounding_poly' => [
                                ['x' => 0.12, 'y' => 0.21],
                                ['x' => 0.81, 'y' => 0.21],
                                ['x' => 0.81, 'y' => 0.77],
                                ['x' => 0.12, 'y' => 0.77],
                            ],
                        ],
                    ],
                    'object_count' => 1,
                    'provider' => 'cloud_vision_ocr',
                    'types' => ['OBJECT_LOCALIZATION'],
                    'raw_response' => [],
                    'record' => [
                        'id' => 100,
                        'image_name' => 'desk.jpg',
                        'image_path' => 'vertex-ocr-images/desk.jpg',
                        'image_url' => '/storage/vertex-ocr-images/desk.jpg',
                        'created_at' => '2026-03-25T12:00:00+00:00',
                    ],
                ]);
        });

        $response = $this->post('/api/google/vertex/image/detect', [
            'image' => UploadedFile::fake()->create('desk.jpg', 100, 'image/jpeg'),
            'types' => ['OBJECT_LOCALIZATION'],
        ], [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Cloud Vision OCR generated',
                'code' => 'cloud_vision_ocr_generated',
                'data' => [
                    'text' => null,
                    'object_count' => 1,
                    'types' => ['OBJECT_LOCALIZATION'],
                    'objects' => [
                        [
                            'name' => 'Laptop',
                        ],
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_vertex_detect_history_returns_recent_records(): void
    {
        VertexOcrResult::factory()->create([
            'image_name' => 'invoice-a.jpg',
            'text' => 'A-001',
        ]);

        VertexOcrResult::factory()->create([
            'image_name' => 'invoice-b.jpg',
            'text' => 'B-001',
        ]);

        $response = $this->get('/api/google/vertex/image/detect/history', [
            'Accept' => 'application/json',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Cloud Vision OCR history loaded',
                'code' => 'cloud_vision_ocr_history_success',
            ])
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        [
                            'id',
                            'image_name',
                            'image_path',
                            'image_url',
                            'provider',
                            'types',
                            'text',
                            'created_at',
                        ],
                    ],
                ],
            ]);
    }
}
