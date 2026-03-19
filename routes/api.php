<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::get('invitations/{token}', [AuthController::class, 'showInvitation'])->where('token', '[A-Za-z0-9]+');
    Route::post('register/invitation', [AuthController::class, 'registerByInvitation']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
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
});
