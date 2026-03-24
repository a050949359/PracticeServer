<?php

namespace App\Http\Controllers\Google\Vertex;

use App\Http\Controllers\Controller;
use App\Http\Requests\Google\Vertex\VertexChatRequest;
use App\Services\Google\Vertex\VertexChatService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VertexChatController extends Controller
{
    public function __construct(private VertexChatService $vertexChatService) {}

    public function store(VertexChatRequest $request): JsonResponse
    {
        try {
            $result = $this->vertexChatService->ask($request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Vertex AI request failed',
                'code' => 'vertex_ai_request_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Vertex AI reply generated',
            'code' => 'vertex_ai_reply_generated',
            'data' => $result,
        ]);
    }
}
