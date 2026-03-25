<?php

namespace App\Http\Controllers\Google\Vertex;

use App\Http\Controllers\Controller;
use App\Http\Requests\Google\Vertex\VertexDetectRequest;
use App\Models\VertexOcrResult;
use App\Services\Google\Vertex\VertexDetectService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class VertexDetectController extends Controller
{
    public function __construct(private VertexDetectService $vertexDetectService) {}

    public function store(VertexDetectRequest $request): JsonResponse
    {
        try {
            $result = $this->vertexDetectService->detect($request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Cloud Vision OCR request failed',
                'code' => 'cloud_vision_ocr_request_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cloud Vision OCR generated',
            'code' => 'cloud_vision_ocr_generated',
            'data' => $result,
        ]);
    }

    public function history(): JsonResponse
    {
        $records = VertexOcrResult::query()
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (VertexOcrResult $record): array {
                return [
                    'id' => $record->id,
                    'image_name' => $record->image_name,
                    'image_path' => $record->image_path,
                    'image_url' => asset('storage/'.$record->image_path),
                    'provider' => $record->provider,
                    'types' => $record->types,
                    'text' => $record->text,
                    'created_at' => $record->created_at?->toISOString(),
                ];
            });

        return response()->json([
            'message' => 'Cloud Vision OCR history loaded',
            'code' => 'cloud_vision_ocr_history_success',
            'data' => [
                'items' => $records,
            ],
        ]);
    }
}
