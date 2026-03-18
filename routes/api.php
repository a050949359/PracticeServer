<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PracticeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);

    Route::group(['middleware' => 'auth:sanctum'], function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::group(['prefix' => 'users'], function () {
    Route::get('/{userId}', [UserController::class, 'getUser'])->whereNumber('userId');
});

Route::group(['prefix' => 'practice', 'as' => 'practice.'], function () {
    Route::post('/echo', [PracticeController::class, 'echoText'])->name('echo');
    Route::post('/sum', [PracticeController::class, 'sumValues'])->name('sum');
});
