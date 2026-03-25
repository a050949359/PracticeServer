<?php

namespace App\Http\Controllers\Google\Vertex;

use App\Http\Controllers\Controller;
use App\Http\Requests\Google\Vertex\VertexImageRequest;
use App\Services\Google\Vertex\VertexImageService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VertexImageController extends Controller
{
    public function __construct(private VertexImageService $vertexImageService) {}

    public function store(VertexImageRequest $request): JsonResponse
    {
        try {
            $result = $this->vertexImageService->ask($request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Vertex AI image request failed',
                'code' => 'vertex_ai_image_request_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Vertex AI image reply generated',
            'code' => 'vertex_ai_image_reply_generated',
            'data' => $result,
        ]);
    }
}
