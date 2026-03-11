<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // $middleware->web(append: [
        //     \App\Http\Middleware\LogRequest::class,
        // ]);
        
        // $middleware->api(append: [
        //     \App\Http\Middleware\ApiLogger::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            // HTTP Exception 類型
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: \Symfony\Component\HttpFoundation\Response::$statusTexts[$status];
                return response()->json([
                    'error' => [
                        'type' => class_basename($e),
                        'message' => $message,
                    ]
                ], $status);
            }

            // Validation Exception
            if ($e instanceof \Illuminate\Validation\ValidationException) {
                return response()->json([
                    'error' => [
                        'type' => class_basename($e),
                        'message' => 'Validation failed',
                        'details' => $e->errors()
                    ]
                ], 422);
            }

            // Authorization Exception (FormRequest authorize())
            if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                return response()->json([
                    'error' => [
                        'type' => class_basename($e),
                        'message' => $e->getMessage() ?: 'Forbidden',
                    ]
                ], 403);
            }

            // 其他 Exception
            Log::error($e); // 記錄到 Log
            return response()->json([
                'error' => [
                    'type' => class_basename($e),
                    'message' => $e->getMessage() ?: 'Internal Server Error',
                ]
            ], 500);
        });
    })->create();
