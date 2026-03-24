<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('auth.verification.verify');
Route::view('/register/reset-password', 'spa')->name('auth.password.reset');
Route::view('/register', 'spa')->name('auth.register');

Route::view('/', 'spa');
Route::view('/google/vertex/chat', 'spa');
Route::view('/register/{any?}', 'spa')->where('any', '.*');
Route::view('/admin/{any?}', 'spa')->where('any', '.*');
