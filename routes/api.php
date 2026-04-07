<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\CsvExport\CsvExportChannelController;
use App\Http\Controllers\CsvExport\CsvExportController;
use App\Http\Controllers\Google\CloudStorage\CloudStorageController;
use App\Http\Controllers\Google\Drive\DriveUploadController;
use App\Http\Controllers\Google\Oauth\GoogleOAuthController;
use App\Http\Controllers\Google\Vertex\VertexChatController;
use App\Http\Controllers\Google\Vertex\VertexDetectController;
use App\Http\Controllers\Google\Vertex\VertexImageController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::get('invitations/{token}', [AuthController::class, 'showInvitation'])->where('token', '[A-Za-z0-9]+');
    Route::post('register/invitation', [AuthController::class, 'registerByInvitation']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('password/forgot', [AuthController::class, 'forgotPassword']);
    Route::post('password/reset', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateMe']);
        Route::post('password/change', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->prefix('user')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('{userId}', [UserController::class, 'getUser'])->whereNumber('userId');
        });
    });
});

Route::middleware(['auth:sanctum', 'staff'])->prefix('admin')->group(function () {
    Route::get('queue/stats', [CsvExportController::class, 'queueStats']);

    Route::prefix('csv-exports')->group(function () {
        Route::get('/', [CsvExportController::class, 'index']);
        Route::post('/', [CsvExportController::class, 'store']);
        Route::get('{csvExportTask}', [CsvExportController::class, 'show'])->whereNumber('csvExportTask');
        Route::get('{csvExportTask}/download', [CsvExportController::class, 'download'])->whereNumber('csvExportTask');
    });

    Route::prefix('csv-channels')->group(function () {
        Route::get('/', [CsvExportChannelController::class, 'index']);
        Route::post('/', [CsvExportChannelController::class, 'store']);
        Route::get('{csvExportChannel}', [CsvExportChannelController::class, 'show'])->whereNumber('csvExportChannel');
        Route::patch('{csvExportChannel}', [CsvExportChannelController::class, 'update'])->whereNumber('csvExportChannel');
        Route::delete('{csvExportChannel}', [CsvExportChannelController::class, 'destroy'])->whereNumber('csvExportChannel');
    });

    Route::prefix('v1')->group(function () {
        Route::post('invitations', [InvitationController::class, 'store']);

        Route::prefix('users')->group(function () {
            Route::get('{userId}', [UserController::class, 'getUser'])->whereNumber('userId');
            Route::post('{userId}/verification-email', [AuthController::class, 'resendVerificationEmailToUser'])->whereNumber('userId');
        });
    });
});

Route::prefix('practice')->group(function () {
    Route::post('echo', [PracticeController::class, 'echoText']);
    Route::post('sum', [PracticeController::class, 'sumValues']);
    Route::post('multiply', [PracticeController::class, 'multiplyValues']);
});

Route::prefix('google/vertex')->group(function () {
    Route::post('chat', [VertexChatController::class, 'store']);
    Route::post('image', [VertexImageController::class, 'store']);
    Route::post('image/detect', [VertexDetectController::class, 'store']);
    Route::get('image/detect/history', [VertexDetectController::class, 'history']);
});

Route::middleware(['auth:sanctum', 'staff'])->prefix('google/drive')->group(function () {
    Route::post('upload', [DriveUploadController::class, 'store']);
    Route::get('files', [DriveUploadController::class, 'index']);
    Route::get('files/{driveFileId}/download', [DriveUploadController::class, 'download'])->where('driveFileId', '[A-Za-z0-9_-]+');
    Route::delete('files/{driveFileId}', [DriveUploadController::class, 'destroy'])->where('driveFileId', '[A-Za-z0-9_-]+');
});

Route::middleware(['auth:sanctum', 'staff'])->prefix('google/oauth')->group(function () {
    Route::get('authorize-url', [GoogleOAuthController::class, 'authorizeUrl']);
    Route::get('status', [GoogleOAuthController::class, 'status']);
    Route::delete('disconnect', [GoogleOAuthController::class, 'disconnect']);
});

Route::middleware(['auth:sanctum', 'staff'])->prefix('google/drive/oauth')->group(function () {
    Route::get('authorize-url', [GoogleOAuthController::class, 'authorizeUrl']);
    Route::get('status', [GoogleOAuthController::class, 'status']);
    Route::delete('disconnect', [GoogleOAuthController::class, 'disconnect']);
});

Route::middleware(['auth:sanctum', 'staff'])->prefix('cloud/storage')->group(function () {
    Route::post('upload', [CloudStorageController::class, 'store']);
    Route::get('files', [CloudStorageController::class, 'index']);
    Route::get('download', [CloudStorageController::class, 'download']);
    Route::delete('file', [CloudStorageController::class, 'destroy']);
});
