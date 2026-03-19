<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureStaffRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => EnsureStaffRole::class,
        ]);

        // $middleware->web(append: [
        //     \App\Http\Middleware\LogRequest::class,
        // ]);

        // $middleware->api(append: [
        //     \App\Http\Middleware\ApiLogger::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
