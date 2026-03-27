<?php

namespace App\Http\Controllers\Google\Drive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Google\Drive\DriveListRequest;
use App\Http\Requests\Google\Drive\DriveUploadRequest;
use App\Services\Google\Drive\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class DriveUploadController extends Controller
{
    public function __construct(private GoogleDriveService $googleDriveService) {}

    public function store(DriveUploadRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'code' => 'unauthenticated',
                ], 401);
            }

            $result = $this->googleDriveService->upload($request->validated(), $user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Google Drive upload failed',
                'code' => 'google_drive_upload_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Google Drive upload success',
            'code' => 'google_drive_upload_success',
            'data' => $result,
        ]);
    }

    public function index(DriveListRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'code' => 'unauthenticated',
                ], 401);
            }

            $result = $this->googleDriveService->list($request->validated(), $user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Google Drive list failed',
                'code' => 'google_drive_list_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Google Drive list success',
            'code' => 'google_drive_list_success',
            'data' => $result,
        ]);
    }

    public function download(Request $request, string $driveFileId): Response|JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'code' => 'unauthenticated',
                ], 401);
            }

            $result = $this->googleDriveService->download($driveFileId, $user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Google Drive download failed',
                'code' => 'google_drive_download_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response($result['content'], 200, [
            'Content-Type' => $result['mime_type'],
            'Content-Disposition' => 'attachment; filename="'.$result['file_name'].'"',
        ]);
    }

    public function destroy(Request $request, string $driveFileId): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated',
                    'code' => 'unauthenticated',
                ], 401);
            }

            $result = $this->googleDriveService->delete($driveFileId, $user);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => 'Google Drive delete failed',
                'code' => 'google_drive_delete_failed',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Google Drive delete success',
            'code' => 'google_drive_delete_success',
            'data' => $result,
        ]);
    }
}
