<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('auth.verification.verify');

Route::view('/', 'spa');
Route::view('/register/{any?}', 'spa')->where('any', '.*');
Route::view('/admin/{any?}', 'spa')->where('any', '.*');
