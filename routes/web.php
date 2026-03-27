<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Google\Oauth\GoogleOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('auth.verification.verify');
Route::get('/auth/google/oauth/callback', [GoogleOAuthController::class, 'callback'])->name('google.oauth.callback');
Route::get('/auth/google/drive/callback', [GoogleOAuthController::class, 'callback'])->name('google.drive.callback');
Route::view('/register/reset-password', 'spa')->name('auth.password.reset');
Route::view('/register', 'spa')->name('auth.register');

Route::view('/', 'spa');
Route::view('/google/{any?}', 'spa')->where('any', '.*');
Route::view('/register/{any?}', 'spa')->where('any', '.*');
Route::view('/admin/{any?}', 'spa')->where('any', '.*');
