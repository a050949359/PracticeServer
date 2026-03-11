<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/{userId}', [UserController::class, 'getUser']);
