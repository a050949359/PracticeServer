<?php

namespace App\Http\Controllers\Google\CloudStorage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Google\CloudStorage\CloudStorageListRequest;
use App\Http\Requests\Google\CloudStorage\CloudStoragePathRequest;
use App\Http\Requests\Google\CloudStorage\CloudStorageUploadRequest;
use App\Services\Google\CloudStorage\CloudStorageService;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CloudStorageController extends Controller
{
    public function __construct(private CloudStorageService $cloudStorageService) {}

    public function store(CloudStorageUploadRequest $request): JsonResponse
    {
        try {
            $result = $this->cloudStorageService->upload($request->validated());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Cloud storage upload failed',
                'code' => 'cloud_storage_upload_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cloud storage upload success',
            'code' => 'cloud_storage_upload_success',
            'data' => $result,
        ]);
    }

    public function index(CloudStorageListRequest $request): JsonResponse
    {
        $result = $this->cloudStorageService->list($request->validated());

        return response()->json([
            'message' => 'Cloud storage list success',
            'code' => 'cloud_storage_list_success',
            'data' => $result,
        ]);
    }

    public function download(CloudStoragePathRequest $request): StreamedResponse|JsonResponse
    {
        try {
            $result = $this->cloudStorageService->download((string) $request->validated('path'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Cloud storage download failed',
                'code' => 'cloud_storage_download_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->streamDownload(function () use ($result): void {
            $stream = $result['stream'];
            fpassthru($stream);
            fclose($stream);
        }, (string) $result['file_name'], [
            'Content-Type' => (string) $result['mime_type'],
        ]);
    }

    public function destroy(CloudStoragePathRequest $request): JsonResponse
    {
        try {
            $result = $this->cloudStorageService->delete((string) $request->validated('path'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Cloud storage delete failed',
                'code' => 'cloud_storage_delete_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Cloud storage delete success',
            'code' => 'cloud_storage_delete_success',
            'data' => $result,
        ]);
    }
}
