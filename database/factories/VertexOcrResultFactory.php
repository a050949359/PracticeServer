<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VertexOcrResult>
 */
class VertexOcrResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'image_name' => fake()->uuid().'.jpg',
            'image_path' => 'vertex-ocr-images/'.fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'image_size' => fake()->numberBetween(1024, 2_000_000),
            'types' => ['DOCUMENT_TEXT_DETECTION'],
            'text' => fake()->sentence(8),
            'provider' => 'cloud_vision_ocr',
            'raw_response' => [
                'fullTextAnnotation' => [
                    'text' => fake()->sentence(8),
                ],
            ],
        ];
    }
}
